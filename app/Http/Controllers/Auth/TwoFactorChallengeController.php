<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactorAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Login-time two-factor challenge. Credentials were already validated;
 * the pending user id lives in the session (never logged in until the
 * code or a recovery code checks out).
 */
class TwoFactorChallengeController extends Controller
{
    private const SESSION_KEY = 'login.two_factor';

    public function __construct(private TwoFactorAuthentication $twoFactor) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->pendingUser($request) === null) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string', 'required_without:code'],
        ]);

        $key = 'two-factor:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                    'minutes' => ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        $valid = isset($validated['code'])
            ? $user->two_factor_secret !== null
                && $this->twoFactor->verify($user->two_factor_secret, $validated['code'])
            : $this->twoFactor->consumeRecoveryCode($user, (string) $validated['recovery_code']);

        if (! $valid) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages([
                'code' => __('The provided two-factor code is invalid.'),
            ]);
        }

        RateLimiter::clear($key);

        $remember = (bool) $request->session()->pull(self::SESSION_KEY.'.remember', false);
        $request->session()->forget(self::SESSION_KEY);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get(self::SESSION_KEY.'.user_id');
        $expiresAt = $request->session()->get(self::SESSION_KEY.'.expires_at', 0);

        if ($userId === null || now()->timestamp > $expiresAt) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return User::find($userId);
    }
}
