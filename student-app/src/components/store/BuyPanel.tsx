"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { BadgeCheck, CheckCircle2, ShieldCheck, Tag } from "lucide-react";
import * as api from "@/lib/api";
import { tokenStore } from "@/lib/api/client";
import { rememberNext } from "@/lib/login-session";
import { inr } from "@/lib/format";
import type { BatchItem, Quote, StartedOrder } from "@/types";
import { Button } from "@/components/ui/Button";

/** The bits of Razorpay's Checkout.js that we use. */
interface RazorpayResponse {
  razorpay_order_id: string;
  razorpay_payment_id: string;
  razorpay_signature: string;
}
interface RazorpayOptions {
  key: string;
  amount: number;
  currency: string;
  order_id: string;
  name: string;
  description: string;
  prefill?: { name?: string; email?: string; contact?: string };
  theme?: { color: string };
  handler: (r: RazorpayResponse) => void;
  modal?: { ondismiss?: () => void };
}
declare global {
  interface Window {
    Razorpay?: new (o: RazorpayOptions) => { open: () => void; on: (e: string, cb: (r: { error?: { description?: string } }) => void) => void };
  }
}

let scriptPromise: Promise<void> | null = null;
function loadRazorpay(): Promise<void> {
  if (typeof window === "undefined") return Promise.reject(new Error("no window"));
  if (window.Razorpay) return Promise.resolve();
  scriptPromise ??= new Promise<void>((resolve, reject) => {
    const s = document.createElement("script");
    s.src = "https://checkout.razorpay.com/v1/checkout.js";
    s.async = true;
    s.onload = () => resolve();
    s.onerror = () => {
      scriptPromise = null;
      reject(new Error("We could not load the payment window. Please check your connection and try again."));
    };
    document.body.appendChild(s);
  });
  return scriptPromise;
}

type Phase = "idle" | "working" | "confirming" | "done" | "pending";

/** Price, coupon and the Buy button for one batch. Logged-out visitors are sent to log in and come straight back. */
export function BuyPanel({ batch: initial }: { batch: BatchItem }) {
  // The page text is cached for a couple of minutes on the server; price, status and ownership are re-read fresh here.
  const [batch, setBatch] = useState(initial);
  const router = useRouter();
  const pathname = usePathname();
  const [signedIn, setSignedIn] = useState(false);
  const [enrolled, setEnrolled] = useState(batch.isEnrolled);
  const [code, setCode] = useState("");
  const [quote, setQuote] = useState<Quote | null>(null);
  const [couponMsg, setCouponMsg] = useState("");
  const [applying, setApplying] = useState(false);
  const [phase, setPhase] = useState<Phase>("idle");
  const [error, setError] = useState("");
  const [receipt, setReceipt] = useState<{ id: number; no: string | null } | null>(null);
  const busy = useRef(false);

  // The page is rendered for everyone; here we learn whether this visitor is logged in and already owns the batch.
  useEffect(() => {
    setSignedIn(!!tokenStore.get());
    api.batches
      .get(initial.slug)
      .then((b) => {
        setBatch(b);
        setEnrolled(b.isEnrolled);
      })
      .catch(() => {});
  }, [initial.slug]);

  const onlineOk = batch.canBuyOnline !== false;
  const open = batch.status === "active";
  const price = quote ? quote.total : batch.price;
  const discount = batch.discountPercent || (batch.mrpPrice > batch.price ? Math.round(((batch.mrpPrice - batch.price) / batch.mrpPrice) * 100) : 0);

  function goLogin() {
    rememberNext(pathname);
    router.push("/login");
  }

  async function applyCoupon() {
    if (!signedIn) return goLogin();
    if (!code.trim()) return;
    setApplying(true);
    setCouponMsg("");
    try {
      const q = await api.checkout.quote(batch.id, code.trim());
      setQuote(q);
      setCouponMsg(`${q.couponCode} applied: you save ${inr(q.discount)}.`);
    } catch (e) {
      setQuote(null);
      setCouponMsg(e instanceof Error ? e.message : "This coupon could not be applied.");
    } finally {
      setApplying(false);
    }
  }

  function removeCoupon() {
    setQuote(null);
    setCode("");
    setCouponMsg("");
  }

  async function buy() {
    if (!signedIn) return goLogin();
    if (busy.current) return;
    busy.current = true;
    setError("");
    setPhase("working");
    try {
      const order = await api.checkout.startOrder(batch.id, quote?.couponCode ?? undefined);
      if (order.free) {
        setReceipt({ id: order.paymentId, no: null });
        setEnrolled(true);
        setPhase("done");
        return;
      }
      await pay(order);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Something went wrong. Please try again.");
      setPhase("idle");
      busy.current = false;
    }
  }

  async function pay(order: StartedOrder) {
    await loadRazorpay();
    if (!window.Razorpay || !order.keyId || !order.orderId) throw new Error("Online payment is not available right now.");

    let prefill: RazorpayOptions["prefill"];
    try {
      const me = await api.profile.getMe();
      prefill = { name: me.name || undefined, email: me.email || undefined, contact: me.phone || undefined };
    } catch {
      prefill = undefined;
    }

    const rz = new window.Razorpay({
      key: order.keyId,
      amount: order.amount,
      currency: order.currency,
      order_id: order.orderId,
      name: order.name,
      description: order.description,
      prefill,
      theme: { color: "#0f766e" },
      modal: {
        ondismiss: () => {
          setPhase((p) => (p === "working" ? "idle" : p));
          busy.current = false;
        },
      },
      handler: async (resp) => {
        setPhase("confirming");
        try {
          const v = await api.checkout.verify(resp);
          setReceipt({ id: v.paymentId, no: v.receiptNo });
          setEnrolled(true);
          setPhase("done");
        } catch {
          // Money may have been taken but we could not confirm it just now: the server matches it by itself shortly.
          setPhase("pending");
        }
      },
    });
    rz.on("payment.failed", (r) => {
      setError(r.error?.description ?? "The payment did not go through. You have not been charged. Please try again.");
      setPhase("idle");
      busy.current = false;
    });
    rz.open();
  }

  // ------------------------------------------------------------------ what to show
  if (phase === "done") {
    return (
      <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 text-center dark:border-emerald-900/40 dark:bg-emerald-950/30" role="status">
        <CheckCircle2 className="mx-auto size-10 text-emerald-600" />
        <h3 className="mt-3 text-lg font-extrabold text-navy-900 dark:text-white">You are enrolled!</h3>
        <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
          {receipt?.no ? `Payment received. Receipt ${receipt.no}.` : "Your courses are ready."}
        </p>
        <div className="mt-5 flex flex-col gap-2">
          <Button href="/my-courses" variant="accent" size="lg">Start learning</Button>
          {receipt?.no && <Button href={`/payments/${receipt.id}`} variant="outline">View receipt</Button>}
        </div>
      </div>
    );
  }

  if (phase === "pending") {
    return (
      <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900/40 dark:bg-amber-950/30" role="status">
        <h3 className="text-base font-extrabold text-navy-900 dark:text-white">We are confirming your payment</h3>
        <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
          If money was taken it is matched automatically within a few minutes and your courses will appear in My Courses. You do not need to pay again.
        </p>
        <Button href="/my-courses" className="mt-4 w-full">Go to My Courses</Button>
      </div>
    );
  }

  return (
    <div className="rounded-2xl border border-line bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
      <div className="flex items-baseline gap-2">
        <span className="font-display text-3xl font-extrabold text-navy-900 dark:text-white">{inr(price)}</span>
        {(quote ? batch.price : batch.mrpPrice) > price && <span className="text-sm text-slate-400 line-through">{inr(quote ? batch.price : batch.mrpPrice)}</span>}
        {!quote && discount > 0 && <span className="rounded-md bg-accent-100 px-1.5 py-0.5 text-xs font-extrabold text-accent-800">{discount}% OFF</span>}
      </div>
      <p className="mt-1 text-xs text-slate-500">{batch.validityText}</p>

      {enrolled ? (
        <div className="mt-5">
          <p className="mb-3 flex items-center gap-2 text-sm font-semibold text-emerald-700"><BadgeCheck className="size-4" /> You already have this batch</p>
          <Button href="/my-courses" size="lg" className="w-full">Go to My Courses</Button>
        </div>
      ) : !open ? (
        <p className="mt-5 rounded-xl bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
          {batch.status === "upcoming" ? "Coming soon. This batch has not opened yet." : "Enrolment for this batch is closed."}
        </p>
      ) : !onlineOk ? (
        <a
          href={batch.purchaseUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-5 inline-flex h-12 w-full items-center justify-center rounded-lg bg-accent-500 px-6 text-base font-semibold text-navy-900 shadow-sm shadow-accent-500/20 transition-colors hover:bg-accent-400"
        >
          Buy now
        </a>
      ) : (
        <>
          <div className="mt-5">
            {quote ? (
              <p className="flex items-center justify-between gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                <span className="flex items-center gap-1.5"><Tag className="size-4" /> {couponMsg}</span>
                <button type="button" onClick={removeCoupon} className="text-xs font-bold underline">Remove</button>
              </p>
            ) : (
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  applyCoupon();
                }}
                className="flex gap-2"
              >
                <label className="sr-only" htmlFor="coupon">Coupon code</label>
                <input
                  id="coupon"
                  value={code}
                  onChange={(e) => setCode(e.target.value.toUpperCase())}
                  placeholder="Coupon code"
                  maxLength={40}
                  autoComplete="off"
                  className="h-10 min-w-0 flex-1 rounded-lg border border-line bg-white px-3 text-sm uppercase placeholder:normal-case focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-100 dark:border-slate-700 dark:bg-slate-950"
                />
                <Button type="submit" variant="outline" loading={applying} disabled={!code.trim()}>Apply</Button>
              </form>
            )}
            {!quote && couponMsg && <p className="mt-1.5 text-xs font-medium text-red-600" role="alert">{couponMsg}</p>}
          </div>

          <Button onClick={buy} variant="accent" size="lg" loading={phase === "working" || phase === "confirming"} className="mt-4 w-full">
            {signedIn ? (price === 0 ? "Enrol for free" : `Pay ${inr(price)}`) : "Log in to buy"}
          </Button>
          {error && <p className="mt-2 text-sm font-medium text-red-600" role="alert">{error}</p>}
          <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-slate-500">
            <ShieldCheck className="size-3.5 text-brand-600" /> Secure payment by Razorpay. UPI, cards, net banking.
          </p>
          {!signedIn && (
            <p className="mt-2 text-center text-xs text-slate-500">
              New here? <Link href="/login" onClick={() => rememberNext(pathname)} className="font-semibold text-brand-600 underline">Sign up with your email</Link>. It takes a minute.
            </p>
          )}
        </>
      )}
    </div>
  );
}
