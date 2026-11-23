<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the student when a payment is confirmed: an in-app note and an e-mail with the receipt summary. */
class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private Payment $payment) {}

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment',
            'title' => 'Payment received',
            'body' => "{$this->payment->item_title}: ₹".number_format($this->payment->amount_paise / 100, 2).'. Your course is ready.',
            'href' => '/payments',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment;
        $mail = (new MailMessage)
            ->subject("Payment received: {$p->item_title}")
            ->greeting('Hi '.($notifiable->nickname ?: $notifiable->name ?: 'there').',')
            ->line("Thank you! We received your payment for “{$p->item_title}”. Your courses are ready in My Courses.")
            ->line("Receipt no.: {$p->receipt_no}")
            ->line('Amount paid: ₹'.number_format($p->amount_paise / 100, 2).($p->discount_paise ? ' (you saved ₹'.number_format($p->discount_paise / 100, 2).($p->coupon_code ? " with {$p->coupon_code}" : '').')' : ''))
            ->action('View receipt', rtrim((string) config('lms.student_app_url'), '/').'/payments');

        return $mail->line('If anything looks wrong, just reply to this e-mail.');
    }
}
