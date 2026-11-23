import type { MetadataRoute } from "next";
import { siteOrigin } from "@/lib/server-api";
import { PRIVATE_PATHS } from "@/lib/seo";

export default async function robots(): Promise<MetadataRoute.Robots> {
  const origin = await siteOrigin();
  return {
    rules: [{ userAgent: "*", allow: ["/", "/store", "/blog"], disallow: [...PRIVATE_PATHS, "/admin"] }],
    sitemap: `${origin}/sitemap.xml`,
  };
}
