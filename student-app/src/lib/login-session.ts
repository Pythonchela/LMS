/** Remembers the email (or phone number) between the login and code screens (kept out of the URL). */
const KEY = "mmn.loginIdentity";

export const loginIdentity = {
  get: () => (typeof window === "undefined" ? null : window.sessionStorage.getItem(KEY)),
  set: (identity: string) => window.sessionStorage.setItem(KEY, identity),
  clear: () => window.sessionStorage.removeItem(KEY),
};

/**
 * Where to go after logging in. A visitor who pressed "Buy" while logged out is sent to /login; once they are in they come
 * back to the page they were on (and not to the dashboard). Only addresses on this site are accepted.
 */
const NEXT_KEY = "mmn.next";

const safe = (path: string | null): string | null => (path && /^\/(?!\/)[^\s]*$/.test(path) && !path.startsWith("/login") ? path : null);

export const rememberNext = (path: string) => {
  const ok = safe(path);
  if (ok && typeof window !== "undefined") window.sessionStorage.setItem(NEXT_KEY, ok);
};

/** The remembered address (once), or the dashboard. */
export const takeNext = (fallback = "/my-courses"): string => {
  if (typeof window === "undefined") return fallback;
  const path = safe(window.sessionStorage.getItem(NEXT_KEY));
  window.sessionStorage.removeItem(NEXT_KEY);
  return path ?? fallback;
};
