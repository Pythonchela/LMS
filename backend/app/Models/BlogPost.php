<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** A public blog article (shown at /blog on the student site). */
class BlogPost extends Model
{
    protected $fillable = [
        'blog_category_id', 'title', 'slug', 'excerpt', 'body', 'cover_image_path', 'cover_image_alt', 'author_name', 'status',
        'published_at', 'seo_title', 'seo_description', 'canonical_url', 'noindex', 'reading_minutes',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'noindex' => 'boolean', 'reading_minutes' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $post) {
            // The address is built from the title unless one was typed, and is kept unique
            $base = Str::slug($post->slug ?: $post->title) ?: 'post';
            $slug = $base;
            $n = 2;
            while (self::where('slug', $slug)->whereKeyNot($post->id ?? 0)->exists()) {
                $slug = $base.'-'.$n++;
            }
            $post->slug = $slug;

            $post->body = HtmlSanitizer::clean($post->body);
            $post->reading_minutes = HtmlSanitizer::readingMinutes($post->body);
            if ($post->status === 'published' && ! $post->published_at) {
                $post->published_at = now();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /** Visible to the public: published, and its publish date has arrived (a future date schedules the post). */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')->where('published_at', '<=', now());
    }

    /** Public address of the cover image, or null. */
    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null;
    }
}
