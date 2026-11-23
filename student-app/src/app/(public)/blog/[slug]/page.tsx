import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ChevronRight, Clock } from "lucide-react";
import { getBlogPost } from "@/lib/public-data";
import { siteOrigin } from "@/lib/server-api";
import { longDate } from "@/lib/format";
import { brand } from "@/lib/config";
import { BlogCardItem } from "@/components/public/BlogList";
import { JsonLd } from "@/components/public/JsonLd";
import { Button } from "@/components/ui/Button";

type Params = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Params): Promise<Metadata> {
  const { slug } = await params;
  const post = await getBlogPost(slug);
  if (!post) return { title: "Article not found", robots: { index: false } };

  const url = post.canonicalUrl || `${await siteOrigin()}/blog/${post.slug}`;
  return {
    title: post.seoTitle,
    description: post.seoDescription,
    alternates: { canonical: url },
    robots: post.noindex ? { index: false, follow: true } : undefined,
    authors: post.author ? [{ name: post.author }] : undefined,
    openGraph: {
      type: "article",
      url,
      title: post.seoTitle,
      description: post.seoDescription,
      siteName: brand.name,
      publishedTime: post.publishedAt ?? undefined,
      modifiedTime: post.updatedAt ?? undefined,
      section: post.category?.name,
      images: post.coverUrl ? [{ url: post.coverUrl, alt: post.coverAlt }] : undefined,
    },
    twitter: { card: post.coverUrl ? "summary_large_image" : "summary", title: post.seoTitle, description: post.seoDescription, images: post.coverUrl ? [post.coverUrl] : undefined },
  };
}

export default async function BlogPostPage({ params }: Params) {
  const { slug } = await params;
  const post = await getBlogPost(slug);
  if (!post) notFound();

  const origin = await siteOrigin();
  const url = `${origin}/blog/${post.slug}`;

  return (
    <>
      <JsonLd
        data={[
          {
            "@context": "https://schema.org",
            "@type": "BlogPosting",
            headline: post.title,
            description: post.seoDescription,
            image: post.coverUrl ? [post.coverUrl] : undefined,
            datePublished: post.publishedAt,
            dateModified: post.updatedAt ?? post.publishedAt,
            author: { "@type": "Person", name: post.author || brand.name },
            publisher: { "@type": "Organization", name: brand.name, url: brand.website, logo: { "@type": "ImageObject", url: `${origin}${brand.logoSrc}` } },
            mainEntityOfPage: { "@type": "WebPage", "@id": url },
            articleSection: post.category?.name,
          },
          {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            itemListElement: [
              { "@type": "ListItem", position: 1, name: "Blog", item: `${origin}/blog` },
              ...(post.category ? [{ "@type": "ListItem", position: 2, name: post.category.name, item: `${origin}/blog/category/${post.category.slug}` }] : []),
              { "@type": "ListItem", position: post.category ? 3 : 2, name: post.title, item: url },
            ],
          },
        ]}
      />

      <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
        <nav aria-label="Breadcrumb" className="mb-6 flex flex-wrap items-center gap-1.5 text-sm text-slate-500">
          <Link href="/blog" className="hover:text-brand-600">Blog</Link>
          {post.category && (
            <>
              <ChevronRight className="size-3.5" />
              <Link href={`/blog/category/${post.category.slug}`} className="hover:text-brand-600">{post.category.name}</Link>
            </>
          )}
        </nav>

        <article>
          <header>
            <h1 className="text-3xl font-extrabold leading-tight text-navy-900 sm:text-4xl dark:text-white">{post.title}</h1>
            <p className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
              {post.author && <span>By <span className="font-semibold text-slate-700 dark:text-slate-300">{post.author}</span></span>}
              {post.publishedAt && <time dateTime={post.publishedAt}>{longDate(post.publishedAt)}</time>}
              <span className="inline-flex items-center gap-1"><Clock className="size-4" /> {post.readingMinutes} min read</span>
            </p>
          </header>

          {post.coverUrl && (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={post.coverUrl} alt={post.coverAlt} width={1200} height={675} className="mt-8 aspect-video w-full rounded-2xl object-cover" />
          )}

          <div className="blog-prose mt-8" dangerouslySetInnerHTML={{ __html: post.body }} />
        </article>

        <aside className="mt-12 rounded-2xl bg-gradient-to-br from-navy-900 via-navy-800 to-brand-700 p-6 text-white sm:p-8">
          <h2 className="text-xl font-extrabold sm:text-2xl">Ready to start preparing?</h2>
          <p className="mt-2 text-slate-200">Notes, practice quizzes and full-length mock tests for IMU-CET, GP Rating and more.</p>
          <Button href="/store" variant="accent" size="lg" className="mt-5">See courses &amp; batches</Button>
        </aside>

        {post.related.length > 0 && (
          <section aria-labelledby="related" className="mt-14">
            <h2 id="related" className="text-xl font-bold text-navy-900 dark:text-white">Keep reading</h2>
            <div className="mt-5 grid gap-6 sm:grid-cols-2">
              {post.related.slice(0, 2).map((p) => <BlogCardItem key={p.id} post={p} />)}
            </div>
          </section>
        )}
      </div>
    </>
  );
}
