<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Public blog for the student site (/blog). Server-rendered there, so search engines read it. Only published posts are ever returned. */
class BlogController extends Controller
{
    private const PER_PAGE = 9;

    /** GET /api/v1/blog?category=slug&q=text&page=1 */
    public function index(Request $request): JsonResponse
    {
        $query = BlogPost::published()->with('category')->orderByDesc('published_at')->orderByDesc('id');

        $category = null;
        if ($slug = $request->query('category')) {
            $category = BlogCategory::where('slug', (string) $slug)->first();
            if (! $category) {
                return response()->json(['message' => 'Category not found.'], 404);
            }
            $query->where('blog_category_id', $category->id);
        }
        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], Str::limit($q, 80, '')).'%';
            $query->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('excerpt', 'like', $like));
        }

        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'data' => collect($page->items())->map(fn (BlogPost $p) => $this->card($p))->values(),
            'meta' => ['page' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()],
            'category' => $category ? ['name' => $category->name, 'slug' => $category->slug, 'description' => $category->description] : null,
        ]);
    }

    /** GET /api/v1/blog/categories — only topics that have a published post. */
    public function categories(): JsonResponse
    {
        $categories = BlogCategory::withCount(['posts' => fn ($q) => $q->published()])->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($c) => $c->posts_count > 0)
            ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'description' => $c->description, 'count' => $c->posts_count])->values();

        return $this->data($categories);
    }

    /** GET /api/v1/blog/posts/{slug} */
    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::published()->with('category')->where('slug', $slug)->firstOrFail();

        $related = BlogPost::published()->with('category')->whereKeyNot($post->id)
            ->when($post->blog_category_id, fn ($q) => $q->orderByRaw('blog_category_id = ? desc', [$post->blog_category_id]))
            ->orderByDesc('published_at')->limit(3)->get()->map(fn (BlogPost $p) => $this->card($p))->values();

        return $this->data($this->card($post) + [
            'body' => $post->body,
            'seoTitle' => $post->seo_title ?: $post->title,
            'seoDescription' => $post->seo_description ?: ($post->excerpt ?: Str::limit(trim(strip_tags($post->body)), 155)),
            'canonicalUrl' => $post->canonical_url,
            'noindex' => (bool) $post->noindex,
            'updatedAt' => $post->updated_at?->toIso8601String(),
            'related' => $related,
        ]);
    }

    /** GET /api/v1/sitemap-data — every public address the sitemap should list. */
    public function sitemap(): JsonResponse
    {
        return $this->data([
            'posts' => BlogPost::published()->where('noindex', false)->orderByDesc('published_at')->get(['slug', 'updated_at', 'published_at'])
                ->map(fn ($p) => ['slug' => $p->slug, 'updatedAt' => ($p->updated_at ?? $p->published_at)?->toIso8601String()])->values(),
            'categories' => BlogCategory::whereHas('posts', fn ($q) => $q->published())->pluck('slug')->values(),
            'batches' => Batch::where('is_live', true)->orderBy('id')->get(['slug', 'updated_at'])
                ->map(fn ($b) => ['slug' => $b->slug, 'updatedAt' => $b->updated_at?->toIso8601String()])->values(),
        ]);
    }

    private function card(BlogPost $p): array
    {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'excerpt' => $p->excerpt ?: Str::limit(trim(strip_tags($p->body)), 160),
            'coverUrl' => $p->cover_url,
            'coverAlt' => $p->cover_image_alt ?: $p->title,
            'author' => $p->author_name,
            'publishedAt' => $p->published_at?->toIso8601String(),
            'readingMinutes' => $p->reading_minutes,
            'category' => $p->category ? ['name' => $p->category->name, 'slug' => $p->category->slug] : null,
        ];
    }
}
