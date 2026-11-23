/** What the public pages show, read on the server (see server-api.ts). */
import type { BatchItem, BlogCard, BlogCategory, BlogPost } from "@/types";
import { serverJson } from "./server-api";

export interface BlogPage {
  data: BlogCard[];
  meta: { page: number; lastPage: number; total: number };
  category: { name: string; slug: string; description: string | null } | null;
}

export const getBlogPage = (opts: { page?: number; category?: string; q?: string } = {}) => {
  const p = new URLSearchParams();
  if (opts.page && opts.page > 1) p.set("page", String(opts.page));
  if (opts.category) p.set("category", opts.category);
  if (opts.q) p.set("q", opts.q);
  return serverJson<BlogPage>(`/blog${p.size ? `?${p}` : ""}`);
};

export const getBlogCategories = async (): Promise<BlogCategory[]> => (await serverJson<{ data: BlogCategory[] }>("/blog/categories"))?.data ?? [];

export const getBlogPost = async (slug: string): Promise<BlogPost | null> =>
  (await serverJson<{ data: BlogPost }>(`/blog/posts/${encodeURIComponent(slug)}`))?.data ?? null;

export const getStoreBatches = async (): Promise<BatchItem[]> => (await serverJson<{ data: BatchItem[] }>("/batches"))?.data ?? [];

export const getStoreBatch = async (slug: string): Promise<BatchItem | null> =>
  (await serverJson<{ data: BatchItem }>(`/batches/${encodeURIComponent(slug)}`))?.data ?? null;

export interface SitemapData {
  posts: { slug: string; updatedAt: string | null }[];
  categories: string[];
  batches: { slug: string; updatedAt: string | null }[];
}

export const getSitemapData = async (): Promise<SitemapData> =>
  (await serverJson<{ data: SitemapData }>("/sitemap-data", 600))?.data ?? { posts: [], categories: [], batches: [] };
