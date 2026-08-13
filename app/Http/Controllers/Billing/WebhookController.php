<?php

namespace App\Http\Controllers\Billing;

use App\Billing\PaymentProviderManager;
use App\Http\Controllers\Controller;
use App\Services\BillingWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public billing webhook endpoint (BILL-019). Verifies the request with the
 * named provider's driver, then hands off to the idempotent processor. Always
 * returns quickly; verification failures are 400, everything verified is 200
 * (including duplicates) so providers stop retrying.
 */
class WebhookController extends Controller
{
    public function __construct(
        private PaymentProviderManager $providers,
        private BillingWebhookProcessor $processor,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        try {
            $driver = $this->providers->driver($provider);
        } catch (\InvalidArgumentException) {
            return response()->json(['error' => 'unknown provider'], 404);
        }

        $event = $driver->verifyWebhook($request);

        if ($event === null) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $outcome = $this->processor->handle($provider, $event);

        $status = $outcome === 'failed' ? 500 : 200;

        return response()->json(['status' => $outcome], $status);
    }
}
