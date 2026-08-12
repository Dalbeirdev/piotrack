<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthentication
{
    private const RECOVERY_CODE_COUNT = 8;

    public function __construct(private Google2FA $engine) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    /**
     * Verify a TOTP code against a secret (±1 time-step window).
     */
    public function verify(string $secret, string $code): bool
    {
        return (bool) $this->engine->verifyKey($secret, $code, window: 1);
    }

    /**
     * otpauth:// provisioning URI rendered as a QR code by the client.
     */
    public function provisioningUri(User $user, string $secret): string
    {
        return $this->engine->getQRCodeUrl(config('app.name'), $user->email, $secret);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return array_map(
            fn (): string => Str::upper(Str::random(5).'-'.Str::random(5)),
            range(1, self::RECOVERY_CODE_COUNT),
        );
    }

    /**
     * Attempt to consume a recovery code; returns true (and persists the
     * shortened list) when the code matched an unused one.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        /** @var list<string> $codes */
        $codes = $user->two_factor_recovery_codes ?? [];

        $remaining = [];
        $matched = false;

        foreach ($codes as $candidate) {
            if (! $matched && hash_equals($candidate, Str::upper(trim($code)))) {
                $matched = true;

                continue;
            }

            $remaining[] = $candidate;
        }

        if ($matched) {
            $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
        }

        return $matched;
    }
}
