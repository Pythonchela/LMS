import Link from "next/link";
import { ChevronLeft, ChevronRight, Clock, Search } from "lucide-react";
import type { BlogCard, BlogCategory } from "@/types";
import { longDate } from "@/lib/format";
import { cn } from "@/lib/utils";

export function BlogCardItem({ post }: { post: BlogCard }) {
  return (
    <article className="group flex flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-xs transition hover:-translate-y-1 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900">
      <Link href={`/blog/${post.slug}`} className="block" tabIndex={-1} aria-hidden="true">
        <div className="aspect-[16/9] bg-gradient-to-br from-navy-800 via-brand-700 to-accent-500">
          {post.coverUrl && (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={post.coverUrl} alt="" width={640} height={360} loading="lazy" className="h-full w-full object-cover" />
          )}
        </div>
      </Link>
      <div className="flex flex-1 flex-col p-5">
        {post.category && (
          <Link href={`/blog/category/${post.category.slug}`} className="text-xs font-bold uppercase tracking-wider text-accent-700 hover:underline">
            {post.category.name}
          </Link>
        )}
        <h2 className="mt-1.5 text-lg font-bold leading-snug text-navy-900 dark:text-white">
          <Link href={`/blog/${post.slug}`} className="hover:text-brand-600">{post.title}</Link>
        </h2>
        <p className="mt-2 line-clamp-3 text-sm leading-relaxed text-slate-500">{post.excerpt}</p>
        <p className="mt-auto flex items-center gap-3 pt-4 text-xs text-slate-400">
          {post.publishedAt && <time dateTime={post.publishedAt}>{longDate(post.publishedAt)}</time>}
          <span className="inline-flex items-center gap-1"><Clock className="size-3.5" /> {post.readingMinutes} min read</span>
        </p>
      </div>
    </article>
  );
}

/** Category chips, search box, the posts and the page links: shared by /blog and /blog/category/<slug>. */
export function BlogList({
  posts,
  categories,
  activeCategory,
  page,
  lastPage,
  q,
  basePath,
}: {
  posts: BlogCard[];
  categories: BlogCategory[];
  activeCategory?: string;
  page: number;
  lastPage: number;
  q?: string;
  basePath: string;
}) {
  const link = (p: number) => {
    const params = new URLSearchParams();
    if (p > 1) params.set("page", String(p));
    if (q) params.set("q", q);
    return `${basePath}${params.size ? `?${params}` : ""}`;
  };

  const chip = (active: boolean) =>
    cn(
      "shrink-0 rounded-full border px-4 py-1.5 text-sm font-semibold transition",
      active ? "border-brand-600 bg-brand-600 text-white" : "border-line bg-white text-slate-600 hover:border-brand-300 hover:text-brand-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300",
    );

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <nav aria-label="Blog categories" className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
          <Link href="/blog" className={chip(!activeCategory)}>All</Link>
          {categories.map((c) => (
            <Link key={c.slug} href={`/blog/category/${c.slug}`} className={chip(activeCategory === c.slug)}>{c.name}</Link>
          ))}
        </nav>
        <form action={basePath} method="get" role="search" className="relative sm:w-64">
          <label htmlFor="blog-search" className="sr-only">Search the blog</label>
          <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
          <input
            id="blog-search"
            name="q"
            defaultValue={q}
            placeholder="Search articles"
            maxLength={80}
            className="h-10 w-full rounded-full border border-line bg-white pl-9 pr-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100 dark:border-slate-700 dark:bg-slate-900"
          />
        </form>
      </div>

      {posts.length === 0 ? (
        <p className="mt-10 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500 dark:border-slate-700 dark:bg-slate-900">
          {q ? `No articles match “${q}”.` : "No articles here yet. Please check back soon."}
        </p>
      ) : (
        <div className="mt-8 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {posts.map((p) => <BlogCardItem key={p.id} post={p} />)}
        </div>
      )}

      {lastPage > 1 && (
        <nav aria-label="Pages" className="mt-10 flex items-center justify-center gap-3 text-sm font-semibold">
          {page > 1 ? (
            <Link href={link(page - 1)} rel="prev" className="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-3.5 py-2 hover:border-brand-300 dark:border-slate-700 dark:bg-slate-900"><ChevronLeft className="size-4" /> Newer</Link>
          ) : <span />}
          <span className="text-slate-500">Page {page} of {lastPage}</span>
          {page < lastPage ? (
            <Link href={link(page + 1)} rel="next" className="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-3.5 py-2 hover:border-brand-300 dark:border-slate-700 dark:bg-slate-900">Older <ChevronRight className="size-4" /></Link>
          ) : <span />}
        </nav>
      )}
    </div>
  );
}
