<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::get('health', HealthController::class)->name('health');

Route::middleware(['auth', 'verified'])->group(function () {
    // Organization lifecycle (no active organization required).
    Route::get('organizations/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('organizations/{organization}/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');

    // Invitation acceptance (authenticated; token resolved across tenants).
    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}', [InvitationAcceptanceController::class, 'accept'])
        ->middleware('throttle:10,1')->name('invitations.accept');

    // Tenant-scoped surfaces require an active organization.
    Route::middleware('organization')->group(function () {
        Route::get('dashboard', function () {
            return Inertia::render('dashboard');
        })->name('dashboard');
    });
});

require __DIR__.'/tenant.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
