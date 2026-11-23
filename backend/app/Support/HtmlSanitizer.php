<?php

namespace App\Support;

/**
 * Cleans HTML written in the admin's rich editor before it is stored and shown to the public: only ordinary formatting
 * tags are kept, and scripts, event handlers (onclick=…), inline styles and javascript: links are removed.
 */
class HtmlSanitizer
{
    private const ALLOWED = '<p><br><h2><h3><h4><ul><ol><li><strong><b><em><i><u><s><a><img><blockquote><pre><code><hr><table><thead><tbody><tr><th><td><figure><figcaption><span><div>';

    public static function clean(?string $html): string
    {
        $html = (string) $html;
        // drop whole script / style blocks, then every tag that is not on the list
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, self::ALLOWED);
        // event handlers and inline styles
        $html = preg_replace('/\s+(on[a-z]+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        // javascript: / data: links and image sources
        $html = preg_replace('/\b(href|src)\s*=\s*("|\')\s*(javascript|vbscript|data):[^"\']*\2/i', '$1=$2#$2', $html) ?? '';

        return trim($html);
    }

    /** Whole minutes of reading at ~200 words a minute (at least 1). */
    public static function readingMinutes(string $html): int
    {
        $words = str_word_count(strip_tags(html_entity_decode($html)));

        return max(1, (int) ceil($words / 200));
    }
}
