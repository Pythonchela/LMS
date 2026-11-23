/**
 * Server-side reads for the public pages (blog, store). These pages are rendered on the server so search engines and link
 * previews get the real text. The backend address is worked out from the address the page was requested on, the same
 * rule the browser uses (lib/api/base-url.ts), so lms.example.com always talks to api.example.com.
 */
import { headers } from "next/headers";
import { isLocalHost, resolveApiBase } from "@/lib/api/base-url";

export interface RequestOrigin {
  hostname: string;
  protocol: string;
  origin: string;
}

export async function requestOrigin(): Promise<RequestOrigin> {
  const h = await headers();
  const host = (h.get("x-forwarded-host") ?? h.get("host") ?? "localhost:3000").split(",")[0]!.trim();
  const hostname = host.replace(/:\d+$/, "");
  const proto = (h.get("x-forwarded-proto") ?? (isLocalHost(hostname) ? "http" : "https")).split(",")[0]!.trim();
  return { hostname, protocol: `${proto}:`, origin: `${proto}://${host}` };
}

/** The public address of this site, without a trailing slash (NEXT_PUBLIC_SITE_URL overrides it). */
export async function siteOrigin(): Promise<string> {
  const fixed = process.env.NEXT_PUBLIC_SITE_URL?.replace(/\/+$/, "");
  return fixed || (await requestOrigin()).origin;
}

/**
 * GET an API path and return the whole JSON body, or null when the server says 404.
 * Any other failure throws, so a hiccup shows an error page (and is not mistaken for "page gone" by search engines).
 */
export async function serverJson<T>(path: string, revalidate = 120): Promise<T | null> {
  const { hostname, protocol } = await requestOrigin();
  const base = resolveApiBase(process.env.NEXT_PUBLIC_API_URL, { hostname, protocol });
  const res = await fetch(`${base}${path}`, { headers: { Accept: "application/json" }, next: { revalidate } });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`The server answered ${res.status} for ${path}`);
  return (await res.json()) as T;
}
