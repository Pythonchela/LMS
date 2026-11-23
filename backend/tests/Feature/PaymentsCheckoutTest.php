<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Buying a batch with Razorpay: the price and coupon rules, signatures, the webhook, free orders and receipts. Razorpay is faked. */
class PaymentsCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Batch $batch;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        config(['lms.billing.razorpay_key_id' => 'rzp_test_abc', 'lms.billing.razorpay_key_secret' => 'secret123', 'lms.billing.razorpay_webhook_secret' => 'hook456']);

        $this->course = Course::create(['title' => 'GP Rating', 'slug' => 'gp-rating']);
        $this->batch = Batch::create(['title' => 'GP Rating Batch', 'slug' => 'gp-rating-batch', 'price' => 1000, 'mrp_price' => 2000, 'is_live' => true]);
        $this->batch->courses()->attach($this->course->id);
    }

    private function student(): User
    {
        return User::factory()->create();
    }

    private function fakeRazorpay(string $orderId = 'order_TEST1'): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory); // a fresh fake each time, so the newest order id wins
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => $orderId, 'amount' => 0, 'currency' => 'INR'], 200)]);
    }

    private function coupon(array $over = []): Coupon
    {
        return Coupon::create($over + ['code' => 'SAVE20', 'type' => 'percent', 'value' => 20, 'per_user_limit' => 1, 'is_active' => true]);
    }

    private function sign(string $orderId, string $paymentId, string $secret = 'secret123'): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);
    }

    /** Starts a purchase and returns the Payment row. */
    private function order(User $user, ?string $code = null, string $orderId = 'order_TEST1'): Payment
    {
        $this->fakeRazorpay($orderId);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/checkout/order', ['batchId' => $this->batch->id, 'coupon' => $code])->assertCreated();

        return Payment::where('razorpay_order_id', $orderId)->firstOrFail();
    }

    // ------------------------------------------------------------------ price and coupons

    public function test_quote_without_and_with_a_coupon(): void
    {
        $this->actingAs($this->student(), 'sanctum');
        $this->coupon();

        $this->postJson('/api/v1/checkout/quote', ['batchId' => $this->batch->id])->assertOk()
            ->assertJsonPath('data.original', 1000)->assertJsonPath('data.discount', 0)->assertJsonPath('data.total', 1000)->assertJsonPath('data.free', false);
        $this->postJson('/api/v1/checkout/quote', ['batchId' => $this->batch->id, 'coupon' => ' save20 '])->assertOk()
            ->assertJsonPath('data.discount', 200)->assertJsonPath('data.total', 800)->assertJsonPath('data.couponCode', 'SAVE20');
    }

    public function test_fixed_and_capped_coupons_never_exceed_the_price(): void
    {
        $this->actingAs($this->student(), 'sanctum');
        $this->coupon(['code' => 'FLAT300', 'type' => 'fixed', 'value' => 300]);
        $this->coupon(['code' => 'HALF', 'type' => 'percent', 'value' => 50, 'max_discount' => 150]);
        $this->coupon(['code' => 'HUGE', 'type' => 'fixed', 'value' => 5000]);

        $this->postJson('/api/v1/checkout/quote', ['batchId' => $this->batch->id, 'coupon' => 'FLAT300'])->assertJsonPath('data.total', 700);
        $this->postJson('/api/v1/checkout/quote', ['batchId' => $this->batch->id, 'coupon' => 'HALF'])->assertJsonPath('data.discount', 150)->assertJsonPath('data.total', 850);
        $this->postJson('/api/v1/checkout/quote', ['batchId' => $this->batch->id, 'coupon' => 'HUGE'])->assertJsonPath('data.total', 0)->assertJsonPath('data.free', true);
    }

    public function test_coupon_rules_are_enforced(): void
    {
        $this->actingAs($this->student(), 'sanctum');
        $other = Batch::create(['title' => 'Other', 'slug' => 'other', 'price' => 500, 'is_live' => true]);
        $this->coupon(['code' => 'OFF', 'is_active' => false]);
        $this->coupon(['code' => 'OLD', 'expires_at' => now()->subDay()]);
        $this->coupon(['code' => 'SOON', 'starts_at' => now()->addDay()]);
        $this->coupon(['code' => 'OTHERONLY', 'batch_id' => $other->id]);
        $this->coupon(['code' => 'BIGONLY', 'min_order' => 5000]);
        $this->coupon(['code' => 'USEDUP', 'max_uses' => 2, 'used_count' => 2]);

        $reasons = ['OFF' => 'not active', 'OLD' => 'expired', 'SOON' => 'not valid yet', 'OTHERONLY' => 'not valid for this batch', 'BIGONLY' => 'at least', 'USEDUP' => 'maximum number', 'NOPE' => 'not valid'];
        foreach ($reasons as $code => $text) {
            $this->postJson('/api/v1/checkout/quote', ['batchId' => $this->batch->id, 'coupon' => $code])->assertUnprocessable()
                ->assertJsonPath('message', fn ($m) => str_contains($m, $text));
        }
    }

    public function test_the_browser_cannot_set_the_amount(): void
    {
        $student = $this->student();
        $this->fakeRazorpay();
        $this->actingAs($student, 'sanctum')->postJson('/api/v1/checkout/order', ['batchId' => $this->batch->id, 'amount' => 1, 'total' => 1, 'price' => 1])->assertCreated();

        $payment = Payment::first();
        $this->assertSame(100000, $payment->amount_paise);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/orders') && $r['amount'] === 100000);
    }

    // ------------------------------------------------------------------ order, verify

    public function test_order_then_verify_gives_access_exactly_once(): void
    {
        $student = $this->student();
        $this->coupon();
        $payment = $this->order($student, 'SAVE20');
        $this->assertSame(80000, $payment->amount_paise);
        $this->assertSame(Payment::CREATED, $payment->status);
        $this->assertNull(Enrollment::where('user_id', $student->id)->first(), 'no access before the payment is confirmed');

        $body = ['razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_ABC', 'razorpay_signature' => $this->sign('order_TEST1', 'pay_ABC')];
        $this->postJson('/api/v1/checkout/verify', $body)->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson('/api/v1/checkout/verify', $body)->assertOk(); // again: nothing doubles

        $payment->refresh();
        $this->assertSame(Payment::PAID, $payment->status);
        $this->assertMatchesRegularExpression('/^MMN-\d{4}-\d{6}$/', $payment->receipt_no);
        $this->assertSame(1, Coupon::first()->used_count);
        $enrollment = Enrollment::where('user_id', $student->id)->where('course_id', $this->course->id)->first();
        $this->assertSame('paid', $enrollment->purchase_type);
        $this->assertSame('order_TEST1', $enrollment->order_id);
        $this->assertEquals(800.00, $enrollment->amount);

        // the student is told, and the sale is written to the activity log
        $this->assertContains('payment', $student->notifications()->get()->pluck('data.type')->all());
        $this->assertDatabaseHas('activity_logs', ['event' => 'payment.paid']);
    }

    public function test_a_wrong_signature_gives_nothing(): void
    {
        $student = $this->student();
        $this->order($student);

        $this->postJson('/api/v1/checkout/verify', ['razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_ABC', 'razorpay_signature' => $this->sign('order_TEST1', 'pay_ABC', 'wrong-secret')])
            ->assertUnprocessable();

        $this->assertSame(Payment::CREATED, Payment::first()->status);
        $this->assertSame(0, Enrollment::count());
    }

    public function test_nobody_can_confirm_another_students_order(): void
    {
        $owner = $this->student();
        $this->order($owner);

        $this->actingAs($this->student(), 'sanctum')->postJson('/api/v1/checkout/verify', [
            'razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => $this->sign('order_TEST1', 'pay_X'),
        ])->assertNotFound();
        $this->assertSame(0, Enrollment::count());
    }

    public function test_payment_is_refused_when_razorpay_is_off_or_the_batch_is_not_sellable(): void
    {
        $student = $this->student();
        $this->actingAs($student, 'sanctum');

        config(['lms.billing.razorpay_key_id' => null]);
        $this->postJson('/api/v1/checkout/order', ['batchId' => $this->batch->id])->assertUnprocessable()->assertJsonPath('message', 'Online payment is not available right now.');

        config(['lms.billing.razorpay_key_id' => 'rzp_test_abc']);
        $empty = Batch::create(['title' => 'Empty', 'slug' => 'empty', 'price' => 100, 'is_live' => true]);
        $this->postJson('/api/v1/checkout/order', ['batchId' => $empty->id])->assertUnprocessable();
        $closed = Batch::create(['title' => 'Closed', 'slug' => 'closed', 'price' => 100, 'is_live' => true, 'status' => 'closed']);
        $closed->courses()->attach($this->course->id);
        $this->postJson('/api/v1/checkout/order', ['batchId' => $closed->id])->assertUnprocessable()->assertJsonPath('message', 'Enrolment for this batch is closed.');
        $hidden = Batch::create(['title' => 'Hidden', 'slug' => 'hidden', 'price' => 100, 'is_live' => false]);
        $this->postJson('/api/v1/checkout/order', ['batchId' => $hidden->id])->assertNotFound();
        $this->assertSame(0, Payment::count());
    }

    public function test_a_failure_at_razorpay_is_reported_calmly(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['description' => 'Authentication failed']], 401)]);

        $this->actingAs($this->student(), 'sanctum')->postJson('/api/v1/checkout/order', ['batchId' => $this->batch->id])
            ->assertUnprocessable()->assertJsonPath('message', 'We could not start the payment. Please try again in a moment.');
    }

    public function test_a_free_total_is_completed_without_razorpay(): void
    {
        Http::fake();
        $student = $this->student();
        $this->coupon(['code' => 'FREE100', 'value' => 100]);

        $this->actingAs($student, 'sanctum')->postJson('/api/v1/checkout/order', ['batchId' => $this->batch->id, 'coupon' => 'FREE100'])
            ->assertCreated()->assertJsonPath('data.free', true)->assertJsonPath('data.keyId', null);

        Http::assertNothingSent();
        $this->assertSame(Payment::PAID, Payment::first()->status);
        $this->assertSame('free', Enrollment::where('user_id', $student->id)->first()->purchase_type);
    }

    public function test_buying_again_extends_instead_of_stacking_a_second_enrollment(): void
    {
        $student = $this->student();
        foreach (['order_A' => 'pay_A', 'order_B' => 'pay_B'] as $order => $pay) {
            $this->order($student, null, $order);
            $this->postJson('/api/v1/checkout/verify', ['razorpay_order_id' => $order, 'razorpay_payment_id' => $pay, 'razorpay_signature' => $this->sign($order, $pay)])->assertOk();
        }

        $this->assertSame(1, Enrollment::where('user_id', $student->id)->count());
        $this->assertSame(2, Payment::where('status', 'paid')->count());
    }

    // ------------------------------------------------------------------ webhook

    private function hook(array $payload, ?string $secret = 'hook456'): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', '/api/v1/payments/webhook/razorpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, (string) $secret)], $body);
    }

    private function captured(string $orderId, int $amount, string $event = 'payment.captured'): array
    {
        return ['event' => $event, 'payload' => ['payment' => ['entity' => ['id' => 'pay_HOOK', 'order_id' => $orderId, 'amount' => $amount, 'method' => 'upi', 'error_description' => 'Bank declined']]]];
    }

    public function test_the_webhook_completes_a_purchase_when_the_browser_never_returned(): void
    {
        $student = $this->student();
        $this->order($student);

        $this->hook($this->captured('order_TEST1', 100000))->assertOk();
        $this->hook($this->captured('order_TEST1', 100000))->assertOk(); // Razorpay retries: still once

        $payment = Payment::first();
        $this->assertSame(Payment::PAID, $payment->status);
        $this->assertSame('upi', $payment->method);
        $this->assertSame('pay_HOOK', $payment->razorpay_payment_id);
        $this->assertSame(1, Enrollment::where('user_id', $student->id)->count());
    }

    public function test_the_webhook_rejects_a_bad_signature_and_a_wrong_amount(): void
    {
        $student = $this->student();
        $this->order($student);

        $this->hook($this->captured('order_TEST1', 100000), 'not-the-secret')->assertStatus(400);
        $this->hook($this->captured('order_TEST1', 100))->assertOk(); // amount does not match the order: ignored

        $this->assertSame(Payment::CREATED, Payment::first()->status);
        $this->assertSame(0, Enrollment::count());
        $this->hook($this->captured('order_UNKNOWN', 100000))->assertOk(); // not ours: answered 200, nothing happens
    }

    public function test_the_webhook_records_failures_and_refunds(): void
    {
        $student = $this->student();
        $this->order($student);
        $this->hook($this->captured('order_TEST1', 100000, 'payment.failed'))->assertOk();
        $this->assertSame(Payment::FAILED, Payment::first()->status);
        $this->assertSame('Bank declined', Payment::first()->failed_reason);

        $paid = $this->order($this->student(), null, 'order_TEST2');
        $this->hook($this->captured('order_TEST2', 100000))->assertOk();
        $this->hook($this->captured('order_TEST2', 100000, 'refund.processed'))->assertOk();
        $this->assertSame(Payment::REFUNDED, $paid->fresh()->status);
    }

    // ------------------------------------------------------------------ payments and receipts

    public function test_payments_and_receipts_belong_to_their_student(): void
    {
        $student = $this->student();
        $this->order($student);
        $this->postJson('/api/v1/checkout/verify', ['razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_ABC', 'razorpay_signature' => $this->sign('order_TEST1', 'pay_ABC')])->assertOk();
        $id = Payment::first()->id;

        $list = $this->getJson('/api/v1/payments')->assertOk()->assertJsonCount(1, 'data')->json('data');
        $this->assertSame(1000, $list[0]['total']);

        config(['lms.billing.gst_percent' => 18, 'lms.billing.business.gstin' => '27ABCDE1234F1Z5']);
        $r = $this->getJson("/api/v1/payments/{$id}/receipt")->assertOk()->json('data');
        $this->assertSame('GP Rating Batch', $r['item']);
        $this->assertSame(847.46, $r['tax']['taxable']);
        $this->assertSame(152.54, $r['tax']['amount']);
        $this->assertSame('27ABCDE1234F1Z5', $r['business']['gstin']);

        $this->actingAs($this->student(), 'sanctum');
        $this->getJson('/api/v1/payments')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/payments/{$id}/receipt")->assertNotFound();
    }

    public function test_checkout_needs_a_login(): void
    {
        $this->postJson('/api/v1/checkout/order', ['batchId' => $this->batch->id])->assertUnauthorized();
        $this->getJson('/api/v1/payments')->assertUnauthorized();
    }

    public function test_the_batch_store_says_whether_it_can_be_bought_here(): void
    {
        $this->getJson('/api/v1/batches/gp-rating-batch')->assertOk()->assertJsonPath('data.canBuyOnline', true);
        config(['lms.billing.razorpay_key_id' => null]);
        $this->getJson('/api/v1/batches/gp-rating-batch')->assertOk()->assertJsonPath('data.canBuyOnline', false);
    }
}
