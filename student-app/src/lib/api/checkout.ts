/** Buying a batch with Razorpay, and the student's payments and receipts (backend: CheckoutController, PaymentController). */
import type { Payment, PaymentReceipt, Quote, StartedOrder, VerifiedPayment } from "@/types";
import { USE_MOCK, mockDelay, request } from "./client";

const post = <T>(path: string, body: unknown) => request<T>(path, { method: "POST", body: JSON.stringify(body) });

export async function quote(batchId: number, coupon?: string): Promise<Quote> {
  if (USE_MOCK) {
    await mockDelay();
    return { original: 2999, discount: coupon ? 300 : 0, total: coupon ? 2699 : 2999, free: false, couponCode: coupon ? coupon.toUpperCase() : null, currency: "INR" };
  }
  return post<Quote>("/checkout/quote", { batchId, coupon: coupon || null });
}

/** Starts the payment. A free total is completed on the spot (`free: true`); otherwise open Razorpay with the result. */
export async function startOrder(batchId: number, coupon?: string): Promise<StartedOrder> {
  if (USE_MOCK) {
    await mockDelay();
    return { free: true, paymentId: 1, orderId: null, amount: 0, currency: "INR", keyId: null, name: "My Merchant Navy", description: "Demo batch" };
  }
  return post<StartedOrder>("/checkout/order", { batchId, coupon: coupon || null });
}

/** Razorpay's window returned: the server checks the signature and gives access. */
export async function verify(p: { razorpay_order_id: string; razorpay_payment_id: string; razorpay_signature: string }): Promise<VerifiedPayment> {
  if (USE_MOCK) return { paymentId: 1, receiptNo: "MMN-DEMO-000001", status: "paid" };
  return post<VerifiedPayment>("/checkout/verify", p);
}

export async function list(): Promise<Payment[]> {
  if (USE_MOCK) {
    await mockDelay();
    return [];
  }
  return request<Payment[]>("/payments");
}

export async function receipt(id: number): Promise<PaymentReceipt> {
  if (USE_MOCK) throw new Error("Receipts need the live server.");
  return request<PaymentReceipt>(`/payments/${id}/receipt`);
}
