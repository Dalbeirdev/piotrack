<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private SubscriptionService $subscriptions,
    ) {}

    /**
     * Change plan / interval (BILL-013) and/or quantity (BILL-014).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::exists('plans', 'code')],
            'interval' => ['required', Rule::in(['monthly', 'annual'])],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $subscription = $this->requireSubscription();
        $plan = Plan::with(['prices', 'entitlements'])->where('code', $validated['plan'])->firstOrFail();

        abort_if($plan->is_custom_priced, 422, 'Enterprise plans are set up by our sales team.');

        $this->subscriptions->changePlan($subscription, $plan, $validated['interval']);

        if (isset($validated['quantity']) && $validated['quantity'] !== $subscription->quantity) {
            $this->subscriptions->changeQuantity($subscription->refresh(), $validated['quantity']);
        }

        return redirect()->route('billing.index')->with('status', __('Your plan has been updated.'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'immediately' => ['nullable', 'boolean'],
        ]);

        $this->subscriptions->cancel($this->requireSubscription(), (bool) ($validated['immediately'] ?? false));

        return back()->with('status', __('Your subscription has been cancelled.'));
    }

    public function resume(): RedirectResponse
    {
        $this->subscriptions->resume($this->requireSubscription());

        return back()->with('status', __('Your subscription has been resumed.'));
    }

    private function requireSubscription(): Subscription
    {
        $subscription = $this->currentOrganization->get()->activeSubscription();

        abort_if($subscription === null, 404, 'No active subscription.');

        return $subscription;
    }
}
