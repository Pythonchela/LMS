import Link from "next/link";
import { brand } from "@/lib/config";
import { Logo } from "@/components/ui/Logo";

export function PublicFooter() {
  return (
    <footer className="mt-16 border-t border-line bg-navy-950 text-slate-300">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr]">
        <div>
          <Logo tone="light" />
          <p className="mt-4 max-w-xs text-sm leading-relaxed text-slate-400">{brand.tagline} Exam preparation for IMU-CET, GP Rating, DNS and more.</p>
        </div>
        <nav aria-label="Explore" className="text-sm">
          <p className="mb-3 font-bold text-white">Explore</p>
          <ul className="space-y-2">
            <li><Link href="/store" className="hover:text-accent-400">Courses &amp; batches</Link></li>
            <li><Link href="/blog" className="hover:text-accent-400">Blog</Link></li>
            <li><Link href="/login" className="hover:text-accent-400">Student login</Link></li>
          </ul>
        </nav>
        <nav aria-label="Company" className="text-sm">
          <p className="mb-3 font-bold text-white">Company</p>
          <ul className="space-y-2">
            <li><a href={brand.website} className="hover:text-accent-400">mymerchantnavy.com</a></li>
            <li><a href={`mailto:${brand.supportEmail}`} className="hover:text-accent-400">{brand.supportEmail}</a></li>
          </ul>
        </nav>
      </div>
      <div className="border-t border-white/10 px-4 py-4 text-center text-xs text-slate-500">© {new Date().getFullYear()} {brand.name}. All rights reserved.</div>
    </footer>
  );
}
