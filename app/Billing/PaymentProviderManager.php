<?php

namespace App\Billing;

use App\Billing\Contracts\PaymentProvider;
use App\Billing\Providers\ManualPaymentProvider;
use App\Billing\Providers\StripePaymentProvider;
use InvalidArgumentException;

/**
 * Resolves the configured payment provider (BILLING_PROVIDER). Bound so that
 * type-hinting PaymentProvider yields the active driver.
 */
class PaymentProviderManager
{
    public function driver(?string $name = null): PaymentProvider
    {
        $name ??= (string) config('billing.provider', 'manual');

        return match ($name) {
            'manual' => new ManualPaymentProvider,
            'stripe' => new StripePaymentProvider,
            default => throw new InvalidArgumentException("Unknown billing provider [{$name}]."),
        };
    }
}
