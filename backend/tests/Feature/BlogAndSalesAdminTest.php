<?php

namespace Tests\Feature;

use App\Filament\Resources\BlogPostResource\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPostResource\Pages\ListBlogPosts;
use App\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Filament\Resources\CouponResource\Pages\ListCoupons;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Admin;
use App\Models\Batch;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The public blog (published posts only, SEO fields), and the admin pages for blog, coupons and payments. */
class BlogAndSalesAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $over = []): BlogPost
    {
        return BlogPost::create($over + ['title' => 'How to join the Merchant Navy', 'body' => '<p>'.str_repeat('word ', 450).'</p>', 'status' => 'published', 'published_at' => now()->subHour()]);
    }

    // ------------------------------------------------------------------ public API

    public function test_only_published_posts_are_public_and_scheduled_ones_wait(): void
    {
        $this->makePost(['title' => 'Live post']);
        $this->makePost(['title' => 'Draft post', 'status' => 'draft']);
        $this->makePost(['title' => 'Future post', 'published_at' => now()->addDay()]);

        $this->getJson('/api/v1/blog')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Live post');
        $this->getJson('/api/v1/blog/posts/draft-post')->assertNotFound();
        $this->getJson('/api/v1/blog/posts/future-post')->assertNotFound();
        $this->getJson('/api/v1/blog/posts/live-post')->assertOk();
    }

    public function test_a_post_carries_what_search_engines_need(): void
    {
        $cat = BlogCategory::create(['name' => 'Careers']);
        $this->makePost(['blog_category_id' => $cat->id, 'seo_title' => 'Join the Merchant Navy in 2027', 'seo_description' => 'A short guide.', 'canonical_url' => 'https://example.com/original']);

        $data = $this->getJson('/api/v1/blog/posts/how-to-join-the-merchant-navy')->assertOk()->json('data');
        $this->assertSame('Join the Merchant Navy in 2027', $data['seoTitle']);
        $this->assertSame('A short guide.', $data['seoDescription']);
        $this->assertSame('https://example.com/original', $data['canonicalUrl']);
        $this->assertSame('careers', $data['category']['slug']);
        $this->assertSame(3, $data['readingMinutes']); // 450 words at 200 a minute
        $this->assertNotEmpty($data['publishedAt']);
    }

    public function test_without_seo_fields_the_title_and_excerpt_are_used(): void
    {
        $this->makePost(['excerpt' => 'Plain excerpt']);
        $data = $this->getJson('/api/v1/blog/posts/how-to-join-the-merchant-navy')->json('data');

        $this->assertSame('How to join the Merchant Navy', $data['seoTitle']);
        $this->assertSame('Plain excerpt', $data['seoDescription']);
    }

    public function test_slugs_stay_unique_and_categories_filter_the_list(): void
    {
        $a = BlogCategory::create(['name' => 'Careers']);
        $b = BlogCategory::create(['name' => 'Exams']);
        $first = $this->makePost(['blog_category_id' => $a->id]);
        $second = $this->makePost(['blog_category_id' => $b->id]); // same title
        $this->assertSame('how-to-join-the-merchant-navy', $first->slug);
        $this->assertSame('how-to-join-the-merchant-navy-2', $second->slug);

        $this->getJson('/api/v1/blog?category=exams')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('category.name', 'Exams');
        $this->getJson('/api/v1/blog?category=nope')->assertNotFound();
        $this->getJson('/api/v1/blog/categories')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_search_and_pagination(): void
    {
        foreach (range(1, 11) as $i) {
            $this->makePost(['title' => "Guide number $i", 'published_at' => now()->subMinutes($i)]);
        }
        $this->makePost(['title' => 'Something else entirely']);

        $this->getJson('/api/v1/blog')->assertOk()->assertJsonCount(9, 'data')->assertJsonPath('meta.lastPage', 2)->assertJsonPath('meta.total', 12);
        $this->getJson('/api/v1/blog?page=2')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/blog?q=entirely')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_sitemap_lists_public_pages_only(): void
    {
        $this->makePost(['title' => 'Public']);
        $this->makePost(['title' => 'Hidden from search', 'noindex' => true]);
        $this->makePost(['title' => 'Draft', 'status' => 'draft']);
        Batch::create(['title' => 'Live batch', 'slug' => 'live-batch', 'price' => 10, 'is_live' => true]);
        Batch::create(['title' => 'Hidden batch', 'slug' => 'hidden-batch', 'price' => 10, 'is_live' => false]);

        $data = $this->getJson('/api/v1/sitemap-data')->assertOk()->json('data');
        $this->assertSame(['public'], array_column($data['posts'], 'slug'));
        $this->assertSame(['live-batch'], array_column($data['batches'], 'slug'));
    }

    public function test_the_blog_html_is_cleaned(): void
    {
        $dirty = '<p onclick="x()" style="color:red">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">bad</a><img src="a.png" onerror="y()"><iframe src="x"></iframe><h2>Title</h2>';
        $clean = HtmlSanitizer::clean($dirty);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('style=', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringContainsString('<h2>Title</h2>', $clean);
        $this->assertStringContainsString('<img src="a.png"', $clean);
    }

    // ------------------------------------------------------------------ admin pages

    private function superAdmin(): Admin
    {
        $admin = Admin::create(['name' => 'Super', 'email' => Admin::SUPER_ADMIN_EMAIL, 'password' => 'secret-password']);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel('admin');

        return $admin;
    }

    public function test_admin_can_write_a_post_and_a_coupon(): void
    {
        $this->superAdmin();

        Livewire::test(CreateBlogPost::class)
            ->fillForm(['title' => 'A new article', 'body' => '<p>Hello there</p>', 'status' => 'published', 'seo_title' => 'Custom title'])
            ->call('create')->assertHasNoFormErrors();
        $post = BlogPost::firstOrFail();
        $this->assertSame('a-new-article', $post->slug);
        $this->assertNotNull($post->published_at);

        Livewire::test(CreateCoupon::class)
            ->fillForm(['code' => ' welcome 20 ', 'type' => 'percent', 'value' => 20, 'per_user_limit' => 1, 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('WELCOME20', Coupon::firstOrFail()->code);

        Livewire::test(ListBlogPosts::class)->assertSuccessful()->assertCanSeeTableRecords([$post]);
        Livewire::test(ListCoupons::class)->assertSuccessful();
    }

    public function test_admin_sees_payments_and_can_record_a_refund(): void
    {
        $admin = $this->superAdmin();
        $student = User::factory()->create();
        $payment = Payment::create([
            'user_id' => $student->id, 'customer_name' => $student->name, 'customer_email' => $student->email, 'item_title' => 'GP Rating Batch',
            'amount_original_paise' => 100000, 'amount_paise' => 80000, 'discount_paise' => 20000, 'status' => Payment::PAID,
            'razorpay_order_id' => 'order_X', 'receipt_no' => 'MMN-2026-000001', 'paid_at' => now(),
        ]);

        Livewire::test(ListPayments::class)->assertSuccessful()->assertCanSeeTableRecords([$payment])
            ->callTableAction('refund', $payment, data: ['note' => 'Asked for money back'])->assertHasNoTableActionErrors();

        $payment->refresh();
        $this->assertSame(Payment::REFUNDED, $payment->status);
        $this->assertSame($admin->id, $payment->refunded_by);
        $this->assertSame('Asked for money back', $payment->refund_note);
    }

    public function test_sales_and_blog_pages_need_their_permission(): void
    {
        $this->actingAs(Admin::create(['name' => 'Support', 'email' => 'support@example.com', 'password' => 'secret-password', 'role' => 'support']), 'admin');
        Filament::setCurrentPanel('admin');

        $this->assertFalse(\App\Filament\Resources\PaymentResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\CouponResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\BlogPostResource::canViewAny());

        $writer = Admin::create(['name' => 'Writer', 'email' => 'writer@example.com', 'password' => 'secret-password', 'permissions' => ['manage_blog']]);
        $this->actingAs($writer, 'admin');
        $this->assertTrue(\App\Filament\Resources\BlogPostResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\PaymentResource::canViewAny());
    }
}
