<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    /** Money received: this month and in total (refunded payments are not counted). */
    public function getSubheading(): string|Htmlable|null
    {
        $paid = Payment::where('status', Payment::PAID);
        $month = (clone $paid)->where('paid_at', '>=', now()->startOfMonth());
        $money = fn (int $paise) => '₹'.number_format($paise / 100, 2);

        return 'Received this month: '.$money((int) $month->sum('amount_paise')).' ('.$month->count().' payments) · In total: '.$money((int) $paid->sum('amount_paise')).' ('.$paid->count().' payments)';
    }
}
