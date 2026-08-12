<?php

use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RecoveryCodeController;
use App\Http\Controllers\Settings\SessionController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::delete('settings/sessions', [SessionController::class, 'destroyOthers'])
        ->name('sessions.destroy-others');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');

    // Sensitive credential management requires recent password confirmation.
    Route::middleware('password.confirm')->group(function () {
        Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
            ->name('two-factor.show');
        Route::post('settings/two-factor', [TwoFactorAuthenticationController::class, 'store'])
            ->name('two-factor.enable');
        Route::post('settings/two-factor/confirm', [TwoFactorAuthenticationController::class, 'confirm'])
            ->middleware('throttle:6,1')
            ->name('two-factor.confirm');
        Route::delete('settings/two-factor', [TwoFactorAuthenticationController::class, 'destroy'])
            ->name('two-factor.disable');
        Route::post('settings/two-factor/recovery-codes', [RecoveryCodeController::class, 'store'])
            ->name('two-factor.recovery-codes');

        Route::get('settings/api-tokens', [ApiTokenController::class, 'index'])
            ->name('api-tokens.index');
        Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])
            ->name('api-tokens.store');
        Route::delete('settings/api-tokens/{tokenId}', [ApiTokenController::class, 'destroy'])
            ->name('api-tokens.destroy');
    });
});
