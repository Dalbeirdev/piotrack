<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Str;

class CouponService
{
    /**
     * Find a redeemable coupon by code, or null.
     */
    public function findRedeemable(?string $code): ?Coupon
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $coupon = Coupon::whereRaw('LOWER(code) = ?', [Str::lower(trim($code))])->first();

        return $coupon !== null && $coupon->isRedeemable() ? $coupon : null;
    }

    public function redeem(Coupon $coupon): void
    {
        $coupon->increment('times_redeemed');
    }
}
