import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getBlogCategories, getBlogPage } from "@/lib/public-data";
import { siteOrigin } from "@/lib/server-api";
import { brand } from "@/lib/config";
import { BlogList } from "@/components/public/BlogList";

type Props = { params: Promise<{ slug: string }>; searchParams: Promise<{ page?: string; q?: string }> };

const pageNo = (v?: string) => Math.max(1, Math.min(500, parseInt(v ?? "1", 10) || 1));

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const { slug } = await params;
  const { page, q } = await searchParams;
  const list = await getBlogPage({ category: slug });
  if (!list?.category) return { title: "Category not found", robots: { index: false } };

  const n = pageNo(page);
  const base = `${await siteOrigin()}/blog/category/${slug}`;
  const title = `${list.category.name}: Merchant Navy Blog`;
  const description = list.category.description || `Articles about ${list.category.name} from ${brand.name}.`;
  const canonical = n > 1 ? `${base}?page=${n}` : base;
  return {
    title: n > 1 ? `${title} (Page ${n})` : title,
    description,
    alternates: { canonical },
    robots: q ? { index: false, follow: true } : undefined,
    openGraph: { type: "website", url: canonical, title, description, siteName: brand.name },
    twitter: { card: "summary", title, description },
  };
}

export default async function BlogCategoryPage({ params, searchParams }: Props) {
  const { slug } = await params;
  const { page, q } = await searchParams;
  const query = (q ?? "").trim().slice(0, 80) || undefined;
  const [list, categories] = await Promise.all([getBlogPage({ category: slug, page: pageNo(page), q: query }), getBlogCategories()]);
  if (!list?.category) notFound();

  return (
    <>
      <section className="bg-gradient-to-br from-navy-900 via-navy-800 to-brand-700 text-white">
        <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
          <p className="text-sm font-bold uppercase tracking-widest text-accent-400">Blog category</p>
          <h1 className="mt-3 text-3xl font-extrabold leading-tight sm:text-5xl">{list.category.name}</h1>
          {list.category.description && <p className="mt-4 max-w-2xl text-base text-slate-200 sm:text-lg">{list.category.description}</p>}
        </div>
      </section>

      <BlogList posts={list.data} categories={categories} activeCategory={slug} page={list.meta.page} lastPage={list.meta.lastPage} q={query} basePath={`/blog/category/${slug}`} />
    </>
  );
}
