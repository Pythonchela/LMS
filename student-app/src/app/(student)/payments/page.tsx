"use client";

import Link from "next/link";
import { ChevronRight, Receipt } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { formatDate } from "@/lib/utils";
import { inr } from "@/lib/format";
import { Badge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { PageHeader } from "@/components/ui/PageHeader";
import { EmptyState, ErrorState, PageSkeleton } from "@/components/ui/States";

/** The student's payments, newest first. Each opens a printable receipt. */
export default function PaymentsPage() {
  const { data: payments, loading, error, reload } = useApi(() => api.checkout.list(), []);

  return (
    <>
      <PageHeader title="Payments" subtitle="Your purchases and receipts" crumbs={[{ label: "Dashboard", href: "/my-courses" }, { label: "Payments" }]} />
      {loading ? (
        <PageSkeleton />
      ) : error ? (
        <ErrorState message={error} onRetry={reload} />
      ) : !payments?.length ? (
        <EmptyState icon={Receipt} title="No payments yet" body="When you buy a batch, your receipt will be here." action={<Button href="/store" variant="accent">See courses &amp; batches</Button>} />
      ) : (
        <ul className="space-y-3">
          {payments.map((p) => (
            <li key={p.id}>
              <Link href={`/payments/${p.id}`} className="flex items-center gap-4 rounded-2xl border border-line bg-white p-4 transition hover:border-brand-200 hover:shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-accent-50 text-accent-700"><Receipt className="size-5" /></span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-bold text-navy-900 dark:text-white">{p.item}</span>
                  <span className="block text-xs text-slate-500">
                    {p.receiptNo} {p.paidAt && <>· {formatDate(p.paidAt)}</>} {p.couponCode && <>· Coupon {p.couponCode}</>}
                  </span>
                </span>
                <span className="text-right">
                  <span className="block font-extrabold text-navy-900 dark:text-white">{inr(p.total)}</span>
                  <Badge tone={p.status === "paid" ? "green" : "orange"}>{p.status === "paid" ? "Paid" : "Refunded"}</Badge>
                </span>
                <ChevronRight className="size-4 shrink-0 text-slate-400" />
              </Link>
            </li>
          ))}
        </ul>
      )}
    </>
  );
}
