<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecoveryCodeController extends Controller
{
    public function __construct(
        private TwoFactorAuthentication $twoFactor,
        private AuditLogger $audit,
    ) {}

    /**
     * Regenerate recovery codes, invalidating all previous ones.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasEnabledTwoFactor(), 400, 'Two-factor authentication is not enabled.');

        $user->forceFill([
            'two_factor_recovery_codes' => $this->twoFactor->generateRecoveryCodes(),
        ])->save();

        $this->audit->log('auth.recovery_codes_regenerated');

        return back();
    }
}
