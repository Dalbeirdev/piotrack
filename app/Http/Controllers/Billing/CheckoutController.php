<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\CouponService;
use App\Services\SubscriptionService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private SubscriptionService $subscriptions,
        private CouponService $coupons,
    ) {}

    /**
     * Order summary before confirming (BILL-008).
     */
    public function show(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::exists('plans', 'code')],
            'interval' => ['required', Rule::in(['monthly', 'annual'])],
        ]);

        $plan = Plan::with(['prices', 'entitlements'])->where('code', $validated['plan'])->firstOrFail();

        if ($plan->is_custom_priced) {
            return redirect()->route('billing.plans')->with('error', __('Contact sales for Enterprise pricing.'));
        }

        return Inertia::render('billing/checkout', [
            'plan' => PlanPresenter::present($plan),
            'interval' => $validated['interval'],
        ]);
    }

    /**
     * Confirm checkout (BILL-008/012). Manual provider activates immediately;
     * a hosted provider returns a redirect URL.
     */
    public function store(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::exists('plans', 'code')],
            'interval' => ['required', Rule::in(['monthly', 'annual'])],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'coupon' => ['nullable', 'string', 'max:64'],
        ]);

        $organization = $this->currentOrganization->get();
        $plan = Plan::with('prices')->where('code', $validated['plan'])->firstOrFail();

        abort_if($plan->is_custom_priced, 422, 'Enterprise plans are set up by our sales team.');

        $coupon = null;
        if (! empty($validated['coupon'])) {
            $coupon = $this->coupons->findRedeemable($validated['coupon']);
            if ($coupon === null) {
                return back()->withErrors(['coupon' => __('That coupon code is not valid.')]);
            }
        }

        $result = $this->subscriptions->checkout(
            $organization,
            $plan,
            $validated['interval'],
            (int) ($validated['quantity'] ?? 1),
            $coupon,
        );

        if (! $result->immediate && $result->redirectUrl !== null) {
            return Inertia::location($result->redirectUrl);
        }

        return redirect()->route('billing.index')->with('status', __('Your subscription is active.'));
    }
}
