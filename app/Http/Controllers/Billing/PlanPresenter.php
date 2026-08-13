<?php

namespace App\Http\Controllers\Billing;

use App\Models\Plan;

/**
 * Serializes a plan (with prices + entitlements) for the frontend.
 */
class PlanPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Plan $plan): array
    {
        $features = [];
        $limits = [];

        foreach ($plan->entitlements as $entitlement) {
            if ($entitlement->kind === 'feature') {
                if ($entitlement->bool_value) {
                    $features[] = $entitlement->key;
                }
            } else {
                $limits[$entitlement->key] = $entitlement->int_value; // null = unlimited
            }
        }

        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'is_custom_priced' => $plan->is_custom_priced,
            'prices' => [
                'monthly' => $plan->priceFor('monthly')?->amount,
                'annual' => $plan->priceFor('annual')?->amount,
            ],
            'features' => $features,
            'limits' => $limits,
        ];
    }
}
