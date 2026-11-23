import type { Metadata } from "next";
import Link from "next/link";
import { ArrowRight, BookOpen, ClipboardList } from "lucide-react";
import { getStoreBatches } from "@/lib/public-data";
import { siteOrigin } from "@/lib/server-api";
import { inr } from "@/lib/format";
import { brand } from "@/lib/config";
import { JsonLd } from "@/components/public/JsonLd";

const TITLE = "Merchant Navy Courses & Batches: IMU-CET, GP Rating, DNS";
const DESCRIPTION =
  "Prepare for IMU-CET, GP Rating, DNS and other Merchant Navy entrance exams with chapter-wise notes, practice quizzes and full-length mock tests. Pick your batch and start today.";

export async function generateMetadata(): Promise<Metadata> {
  const url = `${await siteOrigin()}/store`;
  return {
    title: TITLE,
    description: DESCRIPTION,
    alternates: { canonical: url },
    openGraph: { type: "website", url, title: TITLE, description: DESCRIPTION, siteName: brand.name },
    twitter: { card: "summary_large_image", title: TITLE, description: DESCRIPTION },
  };
}

export default async function StorePage() {
  const batches = await getStoreBatches();
  const origin = await siteOrigin();

  return (
    <>
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "ItemList",
          name: "Merchant Navy exam batches",
          itemListElement: batches.map((b, i) => ({ "@type": "ListItem", position: i + 1, url: `${origin}/store/${b.slug}`, name: b.title })),
        }}
      />

      <section className="bg-gradient-to-br from-navy-900 via-navy-800 to-brand-700 text-white">
        <div className="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20">
          <p className="text-sm font-bold uppercase tracking-widest text-accent-400">Courses &amp; batches</p>
          <h1 className="mt-3 max-w-3xl text-3xl font-extrabold leading-tight sm:text-5xl">Crack your Merchant Navy entrance exam</h1>
          <p className="mt-4 max-w-2xl text-base text-slate-200 sm:text-lg">
            Chapter-wise notes, practice quizzes and full-length mock tests, all in one place. Study on your phone or laptop and track your progress.
          </p>
        </div>
      </section>

      <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        {batches.length === 0 ? (
          <p className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">New batches are coming soon. Please check back shortly.</p>
        ) : (
          <ul className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {batches.map((b) => {
              const discount = b.discountPercent || (b.mrpPrice > b.price ? Math.round(((b.mrpPrice - b.price) / b.mrpPrice) * 100) : 0);
              return (
                <li key={b.id} className="group flex flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-xs transition hover:-translate-y-1 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900">
                  <Link href={`/store/${b.slug}`} className="block">
                    <div className="relative aspect-video bg-gradient-to-br from-navy-800 via-brand-700 to-accent-500">
                      {b.imageUrl && (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={b.imageUrl} alt={b.title} width={640} height={360} loading="lazy" className="h-full w-full object-cover" />
                      )}
                      {b.targetExam && <span className="absolute left-3 top-3 rounded-lg bg-navy-950/80 px-2.5 py-1 text-[11px] font-bold text-accent-300">{b.targetExam}</span>}
                    </div>
                  </Link>
                  <div className="flex flex-1 flex-col p-5">
                    <h2 className="text-lg font-bold leading-snug text-navy-900 dark:text-white">
                      <Link href={`/store/${b.slug}`} className="hover:text-brand-600">{b.title}</Link>
                    </h2>
                    {b.description && <p className="mt-2 line-clamp-2 text-sm text-slate-500">{b.description}</p>}
                    <div className="mt-3 flex flex-wrap gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                      {b.courses.length > 0 && <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 dark:bg-slate-800"><BookOpen className="size-3.5" /> {b.courses.length} course{b.courses.length === 1 ? "" : "s"}</span>}
                      {b.tests.length > 0 && <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 dark:bg-slate-800"><ClipboardList className="size-3.5" /> {b.tests.length} mock test{b.tests.length === 1 ? "" : "s"}</span>}
                    </div>
                    <div className="mt-auto flex items-end justify-between gap-3 border-t border-line pt-4 dark:border-slate-800">
                      <div>
                        <div className="flex items-baseline gap-2">
                          <span className="font-display text-xl font-extrabold text-navy-900 dark:text-white">{inr(b.price)}</span>
                          {b.mrpPrice > b.price && <span className="text-xs text-slate-400 line-through">{inr(b.mrpPrice)}</span>}
                        </div>
                        {discount > 0 && <span className="text-xs font-bold text-accent-700">{discount}% OFF</span>}
                      </div>
                      <Link href={`/store/${b.slug}`} className="inline-flex items-center gap-1.5 rounded-lg bg-accent-500 px-3.5 py-2 text-sm font-bold text-navy-900 transition hover:bg-accent-400">
                        View batch <ArrowRight className="size-4" />
                      </Link>
                    </div>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </div>
    </>
  );
}
