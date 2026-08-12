<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Two-factor enrollment lifecycle (AUTH-010). All routes sit behind the
 * password.confirm middleware; the login-time challenge lives in
 * Auth\TwoFactorChallengeController.
 */
class TwoFactorAuthenticationController extends Controller
{
    public function __construct(
        private TwoFactorAuthentication $twoFactor,
        private AuditLogger $audit,
    ) {}

    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $pendingSecret = $user->two_factor_secret !== null && ! $user->hasEnabledTwoFactor()
            ? $user->two_factor_secret
            : null;

        return Inertia::render('settings/two-factor', [
            'enabled' => $user->hasEnabledTwoFactor(),
            'pendingSecret' => $pendingSecret,
            'provisioningUri' => $pendingSecret !== null
                ? $this->twoFactor->provisioningUri($user, $pendingSecret)
                : null,
            'recoveryCodes' => $user->hasEnabledTwoFactor()
                ? ($user->two_factor_recovery_codes ?? [])
                : [],
        ]);
    }

    /**
     * Begin enrollment: create an unconfirmed secret.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasEnabledTwoFactor()) {
            return back();
        }

        $user->forceFill([
            'two_factor_secret' => $this->twoFactor->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    /**
     * Confirm enrollment with a valid TOTP code; activates 2FA and issues
     * recovery codes.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);

        /** @var User $user */
        $user = $request->user();

        if ($user->two_factor_secret === null || $user->hasEnabledTwoFactor()) {
            return back();
        }

        if (! $this->twoFactor->verify($user->two_factor_secret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => __('The provided two-factor code is invalid.'),
            ]);
        }

        $user->forceFill([
            'two_factor_recovery_codes' => $this->twoFactor->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->audit->log('auth.two_factor_enabled');

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $wasEnabled = $user->hasEnabledTwoFactor();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Cancelling an unconfirmed enrollment is not a security event.
        if ($wasEnabled) {
            $this->audit->log('auth.two_factor_disabled');
        }

        return back();
    }
}
