import { getBlogPage } from "@/lib/public-data";
import { siteOrigin } from "@/lib/server-api";
import { brand } from "@/lib/config";

const esc = (s: string) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");

/** RSS feed of the newest articles: /blog/feed.xml */
export async function GET() {
  const origin = await siteOrigin();
  const list = await getBlogPage();
  const posts = list?.data ?? [];

  const items = posts
    .map(
      (p) => `    <item>
      <title>${esc(p.title)}</title>
      <link>${origin}/blog/${p.slug}</link>
      <guid isPermaLink="true">${origin}/blog/${p.slug}</guid>
      ${p.publishedAt ? `<pubDate>${new Date(p.publishedAt).toUTCString()}</pubDate>` : ""}
      ${p.category ? `<category>${esc(p.category.name)}</category>` : ""}
      <description>${esc(p.excerpt)}</description>
    </item>`,
    )
    .join("\n");

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>${esc(brand.name)} Blog</title>
    <link>${origin}/blog</link>
    <description>Merchant Navy exam preparation and career guides.</description>
    <language>en-in</language>
    <atom:link href="${origin}/blog/feed.xml" rel="self" type="application/rss+xml" />
${items}
  </channel>
</rss>
`;

  return new Response(xml, { headers: { "Content-Type": "application/rss+xml; charset=utf-8", "Cache-Control": "public, max-age=600" } });
}
