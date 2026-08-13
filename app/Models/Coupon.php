<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $expires_at
 */
class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'currency', 'duration',
        'max_redemptions', 'times_redeemed', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'max_redemptions' => 'integer',
            'times_redeemed' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->max_redemptions === null || $this->times_redeemed < $this->max_redemptions;
    }

    /**
     * Discount (minor units) applied to a subtotal.
     */
    public function discountFor(int $subtotal): int
    {
        return $this->type === 'percent'
            ? (int) round($subtotal * min($this->value, 100) / 100)
            : min($this->value, $subtotal);
    }
}
