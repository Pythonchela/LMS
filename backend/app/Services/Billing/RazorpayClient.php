<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The few Razorpay calls the checkout needs: create an order, and check the signatures Razorpay sends back.
 * Keys come from .env (config/lms.php → billing). Nothing here ever logs a key or a secret.
 */
class RazorpayClient
{
    public function enabled(): bool
    {
        return filled(config('lms.billing.razorpay_key_id')) && filled(config('lms.billing.razorpay_key_secret'));
    }

    /** The public key id, which the browser needs to open Razorpay's payment window. */
    public function keyId(): string
    {
        return (string) config('lms.billing.razorpay_key_id');
    }

    /**
     * Creates a Razorpay order for an amount in paise. Razorpay, not us, then collects the money.
     *
     * @param  array<string,string>  $notes  shown in the Razorpay dashboard next to the payment
     * @return array{id:string, amount:int, currency:string}
     */
    public function createOrder(int $amountPaise, string $receipt, array $notes = []): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Online payment is not available right now.');
        }

        $response = Http::withBasicAuth($this->keyId(), (string) config('lms.billing.razorpay_key_secret'))
            ->acceptJson()->timeout(20)
            ->post(rtrim((string) config('lms.billing.razorpay_api'), '/').'/orders', [
                'amount' => $amountPaise,
                'currency' => config('lms.billing.currency', 'INR'),
                'receipt' => $receipt,
                'notes' => $notes,
            ]);

        if (! $response->successful() || ! $response->json('id')) {
            // The reason goes to the log (without any key); the student only sees a calm message.
            Log::warning('Razorpay order was not created', ['status' => $response->status(), 'error' => $response->json('error.description')]);
            throw new RuntimeException('We could not start the payment. Please try again in a moment.');
        }

        return ['id' => (string) $response->json('id'), 'amount' => (int) $response->json('amount'), 'currency' => (string) $response->json('currency')];
    }

    /** True when Razorpay's checkout window really returned this payment for this order (HMAC-SHA256 of "order|payment"). */
    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = (string) config('lms.billing.razorpay_key_secret');
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $orderId.'|'.$paymentId, $secret), $signature);
    }

    /** True when a webhook body really came from Razorpay (HMAC-SHA256 of the raw body with the webhook secret). */
    public function verifyWebhook(string $body, string $signature): bool
    {
        $secret = (string) config('lms.billing.razorpay_webhook_secret');
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }
}
