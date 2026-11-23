"use client";

import { use } from "react";
import { Printer } from "lucide-react";
import * as api from "@/lib/api";
import { useApi } from "@/lib/use-api";
import { formatDate } from "@/lib/utils";
import { inr } from "@/lib/format";
import { brand } from "@/lib/config";
import { Button } from "@/components/ui/Button";
import { PageHeader } from "@/components/ui/PageHeader";
import { ErrorState, PageSkeleton } from "@/components/ui/States";

/** A receipt the student can print or save as PDF (the browser's Print window). The rest of the page is hidden when printing. */
export default function ReceiptPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const { data: r, loading, error, reload } = useApi(() => api.checkout.receipt(Number(id)), [id]);

  return (
    <>
      <div className="print:hidden">
        <PageHeader
          title="Receipt"
          crumbs={[{ label: "Payments", href: "/payments" }, { label: r?.receiptNo ?? "Receipt" }]}
          action={r && <Button variant="outline" onClick={() => window.print()}><Printer className="size-4" /> Print / Save PDF</Button>}
        />
      </div>

      {loading ? (
        <PageSkeleton />
      ) : error || !r ? (
        <ErrorState message={error ?? "Receipt not found"} onRetry={reload} />
      ) : (
        <article className="mx-auto max-w-2xl rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-10 print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none dark:border-slate-800 dark:bg-slate-900">
          <header className="flex flex-wrap items-start justify-between gap-4 border-b border-line pb-6 dark:border-slate-800">
            <div>
              <h2 className="text-xl font-extrabold text-navy-900 dark:text-white">{r.business.name || brand.name}</h2>
              {r.business.address && <p className="mt-1 max-w-xs whitespace-pre-line text-sm text-slate-500">{r.business.address}</p>}
              {r.business.gstin && <p className="mt-1 text-sm text-slate-500">GSTIN: {r.business.gstin}</p>}
              {r.business.email && <p className="text-sm text-slate-500">{r.business.email}</p>}
            </div>
            <div className="text-right">
              <p className="text-xs font-bold uppercase tracking-widest text-accent-700">{r.status === "refunded" ? "Receipt (refunded)" : "Payment receipt"}</p>
              <p className="mt-1 font-mono text-sm font-bold text-navy-900 dark:text-white">{r.receiptNo}</p>
              {r.paidAt && <p className="text-sm text-slate-500">{formatDate(r.paidAt, true)}</p>}
            </div>
          </header>

          <section className="grid gap-6 py-6 sm:grid-cols-2">
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-slate-400">Billed to</p>
              <p className="mt-1 font-semibold text-navy-900 dark:text-white">{r.customer.name || "Student"}</p>
              <p className="text-sm text-slate-500">{r.customer.email}</p>
            </div>
            <div>
              <p className="text-xs font-bold uppercase tracking-wider text-slate-400">Payment</p>
              <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">{r.method && r.method !== "free" ? `Paid by ${r.method.toUpperCase()}` : r.total === 0 ? "No payment needed" : "Paid online"}</p>
              {r.razorpayPaymentId && <p className="break-all font-mono text-xs text-slate-400">Ref: {r.razorpayPaymentId}</p>}
            </div>
          </section>

          <table className="w-full text-sm">
            <thead>
              <tr className="border-y border-line text-left text-xs uppercase tracking-wider text-slate-400 dark:border-slate-800">
                <th className="py-2.5 font-bold">Item</th>
                <th className="py-2.5 text-right font-bold">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td className="py-3 font-semibold text-navy-900 dark:text-white">{r.item}</td>
                <td className="py-3 text-right">{inr(r.original)}</td>
              </tr>
              {r.discount > 0 && (
                <tr>
                  <td className="py-1.5 text-slate-500">Discount {r.couponCode && `(${r.couponCode})`}</td>
                  <td className="py-1.5 text-right text-emerald-700">−{inr(r.discount)}</td>
                </tr>
              )}
              {r.tax && (
                <>
                  <tr>
                    <td className="py-1.5 text-slate-500">Taxable value</td>
                    <td className="py-1.5 text-right">{inr(r.tax.taxable)}</td>
                  </tr>
                  <tr>
                    <td className="py-1.5 text-slate-500">GST @ {r.tax.ratePercent}% (included)</td>
                    <td className="py-1.5 text-right">{inr(r.tax.amount)}</td>
                  </tr>
                </>
              )}
            </tbody>
            <tfoot>
              <tr className="border-t border-line dark:border-slate-800">
                <td className="py-3 text-base font-extrabold text-navy-900 dark:text-white">Total paid</td>
                <td className="py-3 text-right text-base font-extrabold text-navy-900 dark:text-white">{inr(r.total)}</td>
              </tr>
            </tfoot>
          </table>

          <p className="mt-8 text-center text-xs text-slate-400">Thank you for choosing {brand.name}. This is a computer-generated receipt.</p>
        </article>
      )}
    </>
  );
}
