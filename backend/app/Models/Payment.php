<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One checkout of a batch by a student. Amounts are in paise; see the migration. */
class Payment extends Model
{
    public const CREATED = 'created';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

    protected $fillable = [
        'receipt_no', 'user_id', 'customer_name', 'customer_email', 'batch_id', 'item_title', 'coupon_id', 'coupon_code',
        'amount_original_paise', 'discount_paise', 'amount_paise', 'currency', 'status', 'razorpay_order_id', 'razorpay_payment_id',
        'method', 'paid_at', 'failed_reason', 'refunded_at', 'refund_note', 'refunded_by', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_original_paise' => 'integer',
            'discount_paise' => 'integer',
            'amount_paise' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'refunded_by');
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    /** Rupees, for display. */
    public function rupees(int $paise): float
    {
        return round($paise / 100, 2);
    }
}
