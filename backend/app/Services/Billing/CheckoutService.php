<?php

namespace App\Services\Billing;

use App\Exceptions\CouponException;
use App\Models\ActivityLog;
use App\Models\Batch;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentReceived;
use App\Services\Courses\EnrollmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Buying a batch. The server decides everything that matters: the price comes from the batch, the discount from the
 * coupon rules, and access is given only after Razorpay's signature (or its webhook) proves the money was paid.
 * Nothing the browser sends can change an amount.
 */
class CheckoutService
{
    public function __construct(private RazorpayClient $razorpay, private EnrollmentService $enrollments) {}

    /** What the student would pay: amounts in paise and the coupon that was applied. */
    public function quote(User $user, Batch $batch, ?string $couponCode = null): array
    {
        $this->assertPurchasable($batch);

        $original = (int) round((float) $batch->price * 100);
        $coupon = null;
        $discount = 0;

        if (filled($couponCode)) {
            $coupon = Coupon::where('code', Coupon::normalise($couponCode))->first()
                ?? throw new CouponException('This coupon code is not valid.');
            $discount = $coupon->discountFor($user, $batch, $original);
        }

        return ['original' => $original, 'discount' => $discount, 'total' => $original - $discount, 'coupon' => $coupon];
    }

    /**
     * Starts a purchase. A free total (a free batch or a 100% coupon) is completed on the spot; otherwise a Razorpay
     * order is created and the browser opens Razorpay's payment window with it.
     *
     * @return array{free: bool, paymentId: int, orderId: ?string, amount: int, currency: string, keyId: ?string, name: string, description: string}
     */
    public function start(User $user, Batch $batch, ?string $couponCode = null): array
    {
        $quote = $this->quote($user, $batch, $couponCode);

        if ($quote['total'] > 0 && ! $this->razorpay->enabled()) {
            throw new RuntimeException('Online payment is not available right now.');
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'batch_id' => $batch->id,
            'item_title' => $batch->title,
            'coupon_id' => $quote['coupon']?->id,
            'coupon_code' => $quote['coupon']?->code,
            'amount_original_paise' => $quote['original'],
            'discount_paise' => $quote['discount'],
            'amount_paise' => $quote['total'],
            'currency' => config('lms.billing.currency', 'INR'),
            'status' => Payment::CREATED,
        ]);

        if ($quote['total'] === 0) {
            $this->markPaid($payment, null, 'free');

            return $this->response($payment->fresh(), $batch, free: true);
        }

        $order = $this->razorpay->createOrder($quote['total'], 'pay_'.$payment->id, ['payment_id' => (string) $payment->id, 'batch' => $batch->title]);
        $payment->update(['razorpay_order_id' => $order['id']]);

        return $this->response($payment->fresh(), $batch, free: false);
    }

    /** The student's browser says the payment went through: check the signature and give access. Safe to call twice. */
    public function verify(User $user, string $orderId, string $paymentId, string $signature): Payment
    {
        $payment = Payment::where('razorpay_order_id', $orderId)->where('user_id', $user->id)->firstOrFail();

        if ($payment->isPaid()) {
            return $payment;
        }
        if (! $this->razorpay->verifyPayment($orderId, $paymentId, $signature)) {
            throw new RuntimeException('We could not confirm this payment. If money was taken, it will be matched automatically within a few minutes.');
        }

        return $this->markPaid($payment, $paymentId, null);
    }

    /**
     * Razorpay's own notification. This is what completes a purchase when the student closes the browser before it
     * returns. Returns false only for a body that is not from Razorpay.
     */
    public function webhook(string $body, string $signature): bool
    {
        if (! $this->razorpay->verifyWebhook($body, $signature)) {
            return false;
        }

        $event = json_decode($body, true) ?: [];
        $entity = $event['payload']['payment']['entity'] ?? [];
        $orderId = $entity['order_id'] ?? ($event['payload']['order']['entity']['id'] ?? null);
        $payment = $orderId ? Payment::where('razorpay_order_id', $orderId)->first() : null;
        if (! $payment) {
            return true; // not one of ours (or nothing to do): answer 200 so Razorpay stops retrying
        }

        switch ($event['event'] ?? '') {
            case 'payment.captured':
            case 'order.paid':
                if (isset($entity['amount']) && (int) $entity['amount'] !== $payment->amount_paise) {
                    Log::warning('Razorpay webhook amount does not match the order', ['payment' => $payment->id]);
                    break;
                }
                $this->markPaid($payment, $entity['id'] ?? null, $entity['method'] ?? null);
                break;
            case 'payment.failed':
                if ($payment->status === Payment::CREATED) {
                    $payment->update(['status' => Payment::FAILED, 'failed_reason' => $entity['error_description'] ?? 'Payment failed.']);
                }
                break;
            case 'refund.processed':
                if ($payment->isPaid()) {
                    $payment->update(['status' => Payment::REFUNDED, 'refunded_at' => now(), 'refund_note' => 'Refunded from the Razorpay dashboard.']);
                }
                break;
        }

        return true;
    }

    /** Marks a payment as paid exactly once, gives access to the batch's courses and sends the receipt. */
    public function markPaid(Payment $payment, ?string $razorpayPaymentId, ?string $method): Payment
    {
        $fulfilled = DB::transaction(function () use ($payment, $razorpayPaymentId, $method) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === Payment::PAID || $locked->status === Payment::REFUNDED) {
                return null; // already done (the browser and the webhook both arrived)
            }

            $locked->update([
                'status' => Payment::PAID,
                'razorpay_payment_id' => $razorpayPaymentId ?: $locked->razorpay_payment_id,
                'method' => $method ?: $locked->method,
                'paid_at' => now(),
                'failed_reason' => null,
            ]);
            $locked->update(['receipt_no' => sprintf('%s-%s-%06d', config('lms.billing.receipt_prefix', 'MMN'), now()->format('Y'), $locked->id)]);

            if ($locked->coupon_id) {
                Coupon::whereKey($locked->coupon_id)->increment('used_count');
            }
            $this->giveAccess($locked);

            return $locked;
        });

        $payment = $payment->fresh();
        if ($fulfilled) {
            $this->tell($payment);
        }

        return $payment;
    }

    /** Enrols the student in every course of the batch. Each course keeps its own validity rules. */
    private function giveAccess(Payment $payment): void
    {
        $user = $payment->user;
        $courses = $payment->batch?->courses ?? collect();
        if (! $user || $courses->isEmpty()) {
            Log::warning('Payment was taken but there is nothing to enrol', ['payment' => $payment->id]);

            return;
        }

        $share = round($payment->amount_paise / 100 / $courses->count(), 2);
        foreach ($courses as $course) {
            $this->enrollments->assign(
                student: $user,
                course: $course,
                by: null,
                purchaseDate: now(),
                purchaseType: $payment->amount_paise > 0 ? 'paid' : 'free',
                orderId: $payment->razorpay_order_id ?: 'FREE-'.$payment->id,
                paymentId: $payment->razorpay_payment_id,
                amount: $share,
            );
        }
    }

    private function tell(Payment $payment): void
    {
        try {
            ActivityLog::record(
                event: 'payment.paid',
                description: "{$payment->customer_email} bought '{$payment->item_title}' for ₹".number_format($payment->amount_paise / 100, 2)." (receipt {$payment->receipt_no}).",
                subject: $payment,
                properties: ['payment_id' => $payment->id, 'amount_paise' => $payment->amount_paise, 'coupon' => $payment->coupon_code],
                student: $payment->user,
            );
            $payment->user?->notify(new PaymentReceived($payment));
        } catch (\Throwable $e) {
            // A failed e-mail must never undo a payment
            Log::warning('Payment receipt could not be sent', ['payment' => $payment->id, 'error' => $e->getMessage()]);
        }
    }

    private function assertPurchasable(Batch $batch): void
    {
        if (! $batch->is_live) {
            throw new RuntimeException('This batch is not on sale right now.');
        }
        if (($batch->status ?? 'active') !== 'active') {
            throw new RuntimeException($batch->status === 'upcoming' ? 'This batch has not opened yet.' : 'Enrolment for this batch is closed.');
        }
        if (! $batch->courses()->exists()) {
            throw new RuntimeException('This batch is not ready yet. Please check back soon.');
        }
    }

    private function response(Payment $payment, Batch $batch, bool $free): array
    {
        return [
            'free' => $free,
            'paymentId' => $payment->id,
            'orderId' => $payment->razorpay_order_id,
            'amount' => $payment->amount_paise,
            'currency' => $payment->currency,
            'keyId' => $free ? null : $this->razorpay->keyId(),
            'name' => config('lms.billing.business.name', 'My Merchant Navy'),
            'description' => $batch->title,
        ];
    }

    /** Everything a receipt page needs (amounts in rupees). */
    public function receipt(Payment $payment): array
    {
        $business = config('lms.billing.business', []);
        $total = $payment->amount_paise / 100;
        $gst = (float) config('lms.billing.gst_percent', 0);
        $taxable = $gst > 0 ? round($total / (1 + $gst / 100), 2) : null;

        return [
            'id' => $payment->id,
            'receiptNo' => $payment->receipt_no,
            'status' => $payment->status,
            'paidAt' => $payment->paid_at?->toIso8601String(),
            'item' => $payment->item_title,
            'customer' => ['name' => $payment->customer_name, 'email' => $payment->customer_email],
            'currency' => $payment->currency,
            'original' => $payment->amount_original_paise / 100,
            'discount' => $payment->discount_paise / 100,
            'couponCode' => $payment->coupon_code,
            'total' => $total,
            'tax' => $gst > 0 ? ['ratePercent' => $gst, 'taxable' => $taxable, 'amount' => round($total - $taxable, 2)] : null,
            'method' => $payment->method,
            'razorpayPaymentId' => $payment->razorpay_payment_id,
            'orderId' => $payment->razorpay_order_id,
            'business' => [
                'name' => $business['name'] ?? '',
                'address' => $business['address'] ?? '',
                'gstin' => $business['gstin'] ?? '',
                'email' => $business['email'] ?? '',
                'website' => $business['website'] ?? '',
            ],
        ];
    }
}
