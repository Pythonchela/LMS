import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { CheckCircle2, ChevronRight, ClipboardList, BookOpen } from "lucide-react";
import { getStoreBatch } from "@/lib/public-data";
import { siteOrigin } from "@/lib/server-api";
import { brand } from "@/lib/config";
import { BuyPanel } from "@/components/store/BuyPanel";
import { JsonLd } from "@/components/public/JsonLd";

type Params = { params: Promise<{ slug: string }> };

const shorten = (text: string, max = 158) => (text.length <= max ? text : `${text.slice(0, max - 1).replace(/\s+\S*$/, "")}…`);

export async function generateMetadata({ params }: Params): Promise<Metadata> {
  const { slug } = await params;
  const batch = await getStoreBatch(slug);
  if (!batch) return { title: "Batch not found", robots: { index: false } };

  const url = `${await siteOrigin()}/store/${batch.slug}`;
  const title = `${batch.title}${batch.targetExam ? ` (${batch.targetExam})` : ""}`;
  const description = shorten(batch.description || `${batch.title}: notes, practice quizzes and mock tests for ${batch.targetExam ?? "your Merchant Navy exam"}.`);
  return {
    title,
    description,
    alternates: { canonical: url },
    openGraph: { type: "website", url, title, description, siteName: brand.name, images: batch.imageUrl ? [{ url: batch.imageUrl }] : undefined },
    twitter: { card: "summary_large_image", title, description },
  };
}

export default async function BatchPage({ params }: Params) {
  const { slug } = await params;
  const batch = await getStoreBatch(slug);
  if (!batch) notFound();
  const origin = await siteOrigin();
  const url = `${origin}/store/${batch.slug}`;

  return (
    <>
      <JsonLd
        data={[
          {
            "@context": "https://schema.org",
            "@type": "Product",
            name: batch.title,
            description: batch.description || batch.title,
            image: batch.imageUrl ? [batch.imageUrl] : undefined,
            sku: batch.batchCode || String(batch.id),
            brand: { "@type": "Brand", name: brand.name },
            url,
            offers: {
              "@type": "Offer",
              url,
              priceCurrency: "INR",
              price: batch.price,
              availability: batch.status === "active" ? "https://schema.org/InStock" : "https://schema.org/PreOrder",
            },
          },
          {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            itemListElement: [
              { "@type": "ListItem", position: 1, name: "Courses", item: `${origin}/store` },
              { "@type": "ListItem", position: 2, name: batch.title, item: url },
            ],
          },
        ]}
      />

      <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
        <nav aria-label="Breadcrumb" className="mb-5 flex items-center gap-1.5 text-sm text-slate-500">
          <Link href="/store" className="hover:text-brand-600">Courses</Link>
          <ChevronRight className="size-3.5" />
          <span className="truncate font-medium text-slate-700 dark:text-slate-300">{batch.title}</span>
        </nav>

        <div className="grid gap-8 lg:grid-cols-[1fr_22rem] lg:items-start">
          <article>
            {batch.targetExam && <p className="text-sm font-bold uppercase tracking-wider text-accent-700">{batch.targetExam}</p>}
            <h1 className="mt-2 text-3xl font-extrabold leading-tight text-navy-900 sm:text-4xl dark:text-white">{batch.title}</h1>

            <div className="mt-6 aspect-video overflow-hidden rounded-2xl bg-gradient-to-br from-navy-800 via-brand-700 to-accent-500">
              {batch.imageUrl && (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={batch.imageUrl} alt={batch.title} width={1200} height={675} className="h-full w-full object-cover" />
              )}
            </div>

            {batch.description && (
              <section className="mt-8">
                <h2 className="text-xl font-bold text-navy-900 dark:text-white">About this batch</h2>
                <p className="mt-3 whitespace-pre-line leading-relaxed text-slate-600 dark:text-slate-300">{batch.description}</p>
              </section>
            )}

            {batch.highlights.length > 0 && (
              <section className="mt-8">
                <h2 className="text-xl font-bold text-navy-900 dark:text-white">What you get</h2>
                <ul className="mt-4 grid gap-3 sm:grid-cols-2">
                  {batch.highlights.map((h) => (
                    <li key={h} className="flex items-start gap-2.5 rounded-xl border border-line bg-white p-3.5 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                      <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-brand-600" />
                      <span>{h}</span>
                    </li>
                  ))}
                </ul>
              </section>
            )}

            {(batch.courses.length > 0 || batch.tests.length > 0) && (
              <section className="mt-8">
                <h2 className="text-xl font-bold text-navy-900 dark:text-white">Included in this batch</h2>
                <ul className="mt-4 divide-y divide-line overflow-hidden rounded-xl border border-line bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                  {batch.courses.map((c) => (
                    <li key={`c${c.id}`} className="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                      <BookOpen className="size-4 text-brand-600" /> {c.title}
                    </li>
                  ))}
                  {batch.tests.map((t) => (
                    <li key={`t${t.id}`} className="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 dark:text-slate-200">
                      <ClipboardList className="size-4 text-accent-600" /> {t.title}
                      <span className="ml-auto text-xs text-slate-400">{t.durationMinutes} min</span>
                    </li>
                  ))}
                </ul>
              </section>
            )}
          </article>

          <aside className="lg:sticky lg:top-24">
            <BuyPanel batch={batch} />
          </aside>
        </div>
      </div>
    </>
  );
}
