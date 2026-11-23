<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CouponException;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Services\Billing\CheckoutService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Buying a batch: check the price (with a coupon), start the payment, confirm it. All the rules live in CheckoutService. */
class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $checkout) {}

    /** POST /api/v1/checkout/quote — what the student would pay, with the coupon applied. */
    public function quote(Request $request): JsonResponse
    {
        [$batch, $coupon] = $this->input($request);

        return $this->attempt(function () use ($request, $batch, $coupon) {
            $q = $this->checkout->quote($request->user(), $batch, $coupon);

            return $this->data([
                'original' => $q['original'] / 100,
                'discount' => $q['discount'] / 100,
                'total' => $q['total'] / 100,
                'free' => $q['total'] === 0,
                'couponCode' => $q['coupon']?->code,
                'currency' => config('lms.billing.currency', 'INR'),
            ]);
        });
    }

    /** POST /api/v1/checkout/order — starts the payment (free totals are completed straight away). */
    public function order(Request $request): JsonResponse
    {
        [$batch, $coupon] = $this->input($request);

        return $this->attempt(fn () => $this->data($this->checkout->start($request->user(), $batch, $coupon), 201));
    }

    /** POST /api/v1/checkout/verify — Razorpay's window returned: confirm the signature and give access. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:80'],
            'razorpay_payment_id' => ['required', 'string', 'max:80'],
            'razorpay_signature' => ['required', 'string', 'max:200'],
        ]);

        return $this->attempt(function () use ($request, $data) {
            $payment = $this->checkout->verify($request->user(), $data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature']);

            return $this->data(['paymentId' => $payment->id, 'receiptNo' => $payment->receipt_no, 'status' => $payment->status]);
        });
    }

    /** @return array{0: Batch, 1: ?string} */
    private function input(Request $request): array
    {
        $data = $request->validate(['batchId' => ['required', 'integer'], 'coupon' => ['nullable', 'string', 'max:40']]);

        return [Batch::where('is_live', true)->findOrFail($data['batchId']), $data['coupon'] ?? null];
    }

    /** Turns "the student cannot do that" into a 422 with a readable message. */
    private function attempt(callable $do): JsonResponse
    {
        try {
            return $do();
        } catch (ModelNotFoundException $e) {
            throw $e; // an unknown or someone else's order is a 404, not a message for the student
        } catch (CouponException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
