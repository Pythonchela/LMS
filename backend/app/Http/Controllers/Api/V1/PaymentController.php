<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Billing\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The student's own payments and receipts, and Razorpay's webhook. */
class PaymentController extends Controller
{
    public function __construct(private CheckoutService $checkout) {}

    /** GET /api/v1/payments — the student's completed payments, newest first. */
    public function index(Request $request): JsonResponse
    {
        $payments = Payment::where('user_id', $request->user()->id)->whereIn('status', [Payment::PAID, Payment::REFUNDED])
            ->latest('paid_at')->latest('id')->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'receiptNo' => $p->receipt_no,
                'item' => $p->item_title,
                'total' => $p->amount_paise / 100,
                'discount' => $p->discount_paise / 100,
                'couponCode' => $p->coupon_code,
                'status' => $p->status,
                'paidAt' => $p->paid_at?->toIso8601String(),
            ])->values()->all();

        return $this->data($payments);
    }

    /** GET /api/v1/payments/{id}/receipt */
    public function receipt(Request $request, int $id): JsonResponse
    {
        $payment = Payment::where('user_id', $request->user()->id)->whereIn('status', [Payment::PAID, Payment::REFUNDED])->findOrFail($id);

        return $this->data($this->checkout->receipt($payment));
    }

    /** POST /api/v1/payments/webhook/razorpay — public; trusted only when the signature proves it came from Razorpay. */
    public function webhook(Request $request): Response
    {
        $ok = $this->checkout->webhook($request->getContent(), (string) $request->header('X-Razorpay-Signature'));

        return response($ok ? 'ok' : 'invalid signature', $ok ? 200 : 400);
    }
}
