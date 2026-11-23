<?php

namespace App\Models;

use App\Exceptions\CouponException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A discount code typed at checkout. See the migration for what each column means. */
class Coupon extends Model
{
    protected $fillable = ['code', 'description', 'type', 'value', 'max_discount', 'min_order', 'max_uses', 'per_user_limit', 'used_count', 'starts_at', 'expires_at', 'batch_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_order' => 'decimal:2',
            'max_uses' => 'integer',
            'per_user_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Codes are not case sensitive: they are stored (and looked up) in capitals, without spaces. */
    public static function normalise(?string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $code));
    }

    public function setCodeAttribute(?string $code): void
    {
        $this->attributes['code'] = self::normalise($code);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Checks that this student may use the code on this batch at this price, and returns the discount in paise.
     *
     * @throws CouponException with a message a student can read
     */
    public function discountFor(User $user, Batch $batch, int $pricePaise): int
    {
        $now = now();
        if (! $this->is_active) {
            throw new CouponException('This coupon is not active.');
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            throw new CouponException('This coupon is not valid yet.');
        }
        if ($this->expires_at && $this->expires_at->lt($now)) {
            throw new CouponException('This coupon has expired.');
        }
        if ($this->batch_id && $this->batch_id !== $batch->id) {
            throw new CouponException('This coupon is not valid for this batch.');
        }
        if ($this->min_order !== null && $pricePaise < (int) round((float) $this->min_order * 100)) {
            throw new CouponException('This coupon needs an order of at least ₹'.number_format((float) $this->min_order, 0).'.');
        }
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            throw new CouponException('This coupon has been used the maximum number of times.');
        }
        if ($this->per_user_limit > 0) {
            $mine = $this->payments()->where('user_id', $user->id)->where('status', 'paid')->count();
            if ($mine >= $this->per_user_limit) {
                throw new CouponException('You have already used this coupon.');
            }
        }

        $discount = $this->type === 'fixed'
            ? (int) round((float) $this->value * 100)
            : (int) floor($pricePaise * ((float) $this->value) / 100);
        if ($this->type !== 'fixed' && $this->max_discount !== null) {
            $discount = min($discount, (int) round((float) $this->max_discount * 100));
        }

        return max(0, min($discount, $pricePaise));
    }
}
