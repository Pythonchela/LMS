import type { MetadataRoute } from "next";
import { getSitemapData } from "@/lib/public-data";
import { siteOrigin } from "@/lib/server-api";

/** Every public address, for search engines: /sitemap.xml. Private pages (the student's own area) are not listed. */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const origin = await siteOrigin();
  const data = await getSitemapData().catch(() => ({ posts: [], categories: [], batches: [] }));
  const when = (iso: string | null) => (iso ? new Date(iso) : undefined);

  return [
    { url: `${origin}/store`, changeFrequency: "weekly", priority: 0.9 },
    { url: `${origin}/blog`, changeFrequency: "daily", priority: 0.8 },
    ...data.batches.map((b) => ({ url: `${origin}/store/${b.slug}`, lastModified: when(b.updatedAt), changeFrequency: "weekly" as const, priority: 0.8 })),
    ...data.categories.map((slug) => ({ url: `${origin}/blog/category/${slug}`, changeFrequency: "weekly" as const, priority: 0.5 })),
    ...data.posts.map((p) => ({ url: `${origin}/blog/${p.slug}`, lastModified: when(p.updatedAt), changeFrequency: "monthly" as const, priority: 0.7 })),
  ];
}
