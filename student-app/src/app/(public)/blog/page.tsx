import type { Metadata } from "next";
import { getBlogCategories, getBlogPage } from "@/lib/public-data";
import { siteOrigin } from "@/lib/server-api";
import { brand } from "@/lib/config";
import { BlogList } from "@/components/public/BlogList";
import { JsonLd } from "@/components/public/JsonLd";

type Search = { searchParams: Promise<{ page?: string; q?: string }> };

const TITLE = "Merchant Navy Blog: Exam Tips, Careers and Preparation Guides";
const DESCRIPTION = "Articles on IMU-CET, GP Rating, DNS sponsorship and life at sea: how to prepare, what to study and how to start your Merchant Navy career.";

const pageNo = (v?: string) => Math.max(1, Math.min(500, parseInt(v ?? "1", 10) || 1));

export async function generateMetadata({ searchParams }: Search): Promise<Metadata> {
  const { page, q } = await searchParams;
  const n = pageNo(page);
  const base = `${await siteOrigin()}/blog`;
  const canonical = n > 1 ? `${base}?page=${n}` : base;
  return {
    title: n > 1 ? `${TITLE} (Page ${n})` : TITLE,
    description: DESCRIPTION,
    alternates: { canonical, types: { "application/rss+xml": `${base}/feed.xml` } },
    robots: q ? { index: false, follow: true } : undefined, // search result pages are not worth indexing
    openGraph: { type: "website", url: canonical, title: TITLE, description: DESCRIPTION, siteName: brand.name },
    twitter: { card: "summary", title: TITLE, description: DESCRIPTION },
  };
}

export default async function BlogIndex({ searchParams }: Search) {
  const { page, q } = await searchParams;
  const query = (q ?? "").trim().slice(0, 80) || undefined;
  const [list, categories] = await Promise.all([getBlogPage({ page: pageNo(page), q: query }), getBlogCategories()]);
  const origin = await siteOrigin();

  return (
    <>
      <JsonLd data={{ "@context": "https://schema.org", "@type": "Blog", name: `${brand.name} Blog`, url: `${origin}/blog`, description: DESCRIPTION, publisher: { "@type": "Organization", name: brand.name, url: brand.website } }} />

      <section className="bg-gradient-to-br from-navy-900 via-navy-800 to-brand-700 text-white">
        <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
          <p className="text-sm font-bold uppercase tracking-widest text-accent-400">The blog</p>
          <h1 className="mt-3 max-w-3xl text-3xl font-extrabold leading-tight sm:text-5xl">Merchant Navy preparation, explained simply</h1>
          <p className="mt-4 max-w-2xl text-base text-slate-200 sm:text-lg">{DESCRIPTION}</p>
        </div>
      </section>

      <BlogList posts={list?.data ?? []} categories={categories} page={list?.meta.page ?? 1} lastPage={list?.meta.lastPage ?? 1} q={query} basePath="/blog" />
    </>
  );
}
