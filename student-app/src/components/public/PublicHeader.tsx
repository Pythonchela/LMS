"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Menu, X } from "lucide-react";
import { Logo } from "@/components/ui/Logo";
import { Button } from "@/components/ui/Button";
import { tokenStore } from "@/lib/api/client";

const links = [
  { label: "Courses", href: "/store" },
  { label: "Blog", href: "/blog" },
];

/** Top bar of the public pages (store and blog). Shows "My Courses" to a logged-in student and "Log in" to everyone else. */
export function PublicHeader() {
  const [open, setOpen] = useState(false);
  const [signedIn, setSignedIn] = useState(false);

  useEffect(() => setSignedIn(!!tokenStore.get()), []);

  return (
    <header className="sticky top-0 z-30 border-b border-line bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90">
      <div className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6">
        <Link href="/store" aria-label="My Merchant Navy home">
          <Logo />
        </Link>

        <nav aria-label="Main" className="ml-4 hidden items-center gap-1 md:flex">
          {links.map((l) => (
            <Link key={l.href} href={l.href} className="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-navy-900 dark:text-slate-300 dark:hover:bg-slate-800">
              {l.label}
            </Link>
          ))}
        </nav>

        <div className="ml-auto hidden items-center gap-2 md:flex">
          {signedIn ? (
            <Button href="/my-courses" size="sm">My Courses</Button>
          ) : (
            <>
              <Button href="/login" variant="ghost" size="sm">Log in</Button>
              <Button href="/login" variant="accent" size="sm">Get started</Button>
            </>
          )}
        </div>

        <button
          type="button"
          onClick={() => setOpen((o) => !o)}
          className="ml-auto grid size-10 place-items-center rounded-xl text-slate-600 hover:bg-slate-100 md:hidden dark:text-slate-300 dark:hover:bg-slate-800"
          aria-label={open ? "Close menu" : "Open menu"}
          aria-expanded={open}
        >
          {open ? <X className="size-5" /> : <Menu className="size-5" />}
        </button>
      </div>

      {open && (
        <div className="border-t border-line bg-white px-4 py-3 md:hidden dark:border-slate-800 dark:bg-slate-900">
          <nav aria-label="Mobile" className="flex flex-col gap-1">
            {links.map((l) => (
              <Link key={l.href} href={l.href} onClick={() => setOpen(false)} className="rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                {l.label}
              </Link>
            ))}
            <Link href={signedIn ? "/my-courses" : "/login"} onClick={() => setOpen(false)} className="mt-1 rounded-lg bg-accent-500 px-3 py-2.5 text-center text-sm font-bold text-navy-900">
              {signedIn ? "My Courses" : "Log in / Get started"}
            </Link>
          </nav>
        </div>
      )}
    </header>
  );
}
