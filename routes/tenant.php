<?php

use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\BillingProfileController;
use App\Http\Controllers\Billing\CheckoutController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\InvitationController;
use App\Http\Controllers\Settings\MemberController;
use App\Http\Controllers\Settings\OrganizationSettingsController;
use App\Http\Controllers\Settings\TeamController;
use Illuminate\Support\Facades\Route;

/*
 * Tenant-scoped organization management. Every route requires an authenticated,
 * verified user with an active organization; individual actions are gated by
 * RBAC permissions (RBAC-004). Route-model binding for {invitation} and {team}
 * is automatically scoped to the current tenant via BelongsToTenant.
 */
Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    // Organization profile & deletion.
    Route::get('settings/organization', [OrganizationSettingsController::class, 'edit'])
        ->middleware('can:organization.view')->name('organization.edit');
    Route::patch('settings/organization', [OrganizationSettingsController::class, 'update'])
        ->middleware('can:organization.update')->name('organization.update');
    Route::delete('settings/organization', [OrganizationSettingsController::class, 'destroy'])
        ->middleware('can:organization.delete')->name('organization.destroy');

    // Members.
    Route::get('settings/members', [MemberController::class, 'index'])
        ->middleware('can:members.view')->name('members.index');
    Route::patch('settings/members/{member}/role', [MemberController::class, 'updateRole'])
        ->middleware('can:members.update')->name('members.role');
    Route::patch('settings/members/{member}/deactivate', [MemberController::class, 'deactivate'])
        ->middleware('can:members.update')->name('members.deactivate');
    Route::patch('settings/members/{member}/reactivate', [MemberController::class, 'reactivate'])
        ->middleware('can:members.update')->name('members.reactivate');
    Route::delete('settings/members/{member}', [MemberController::class, 'destroy'])
        ->middleware('can:members.remove')->name('members.destroy');

    // Invitations.
    Route::post('settings/members/invitations', [InvitationController::class, 'store'])
        ->middleware('can:members.invite')->name('invitations.store');
    Route::post('settings/members/invitations/{invitation}/resend', [InvitationController::class, 'resend'])
        ->middleware('can:members.invite')->name('invitations.resend');
    Route::delete('settings/members/invitations/{invitation}', [InvitationController::class, 'destroy'])
        ->middleware('can:members.invite')->name('invitations.destroy');

    // Teams (also gated by the `teams` feature entitlement).
    Route::middleware('entitlement:teams')->group(function () {
        Route::get('settings/teams', [TeamController::class, 'index'])
            ->middleware('can:teams.view')->name('teams.index');
        Route::post('settings/teams', [TeamController::class, 'store'])
            ->middleware('can:teams.manage')->name('teams.store');
        Route::patch('settings/teams/{team}', [TeamController::class, 'update'])
            ->middleware('can:teams.manage')->name('teams.update');
        Route::delete('settings/teams/{team}', [TeamController::class, 'destroy'])
            ->middleware('can:teams.manage')->name('teams.destroy');
        Route::post('settings/teams/{team}/members', [TeamController::class, 'addMember'])
            ->middleware('can:teams.manage')->name('teams.members.add');
        Route::delete('settings/teams/{team}/members/{member}', [TeamController::class, 'removeMember'])
            ->middleware('can:teams.manage')->name('teams.members.remove');
    });

    // Audit log viewer (also gated by the `audit_log` feature entitlement).
    Route::get('settings/audit-log', [AuditLogController::class, 'index'])
        ->middleware(['can:audit.view', 'entitlement:audit_log'])->name('audit.index');

    // Billing & subscriptions.
    Route::get('billing', [BillingController::class, 'index'])
        ->middleware('can:billing.view')->name('billing.index');
    Route::get('billing/plans', [BillingController::class, 'plans'])
        ->middleware('can:billing.view')->name('billing.plans');
    Route::get('billing/invoices', [InvoiceController::class, 'index'])
        ->middleware('can:billing.view')->name('billing.invoices.index');
    Route::get('billing/invoices/{invoice}', [InvoiceController::class, 'show'])
        ->middleware('can:billing.view')->name('billing.invoices.show');

    Route::middleware('can:billing.manage')->group(function () {
        Route::get('billing/checkout', [CheckoutController::class, 'show'])->name('billing.checkout.show');
        Route::post('billing/checkout', [CheckoutController::class, 'store'])->name('billing.checkout.store');
        Route::patch('billing/subscription', [SubscriptionController::class, 'update'])->name('billing.subscription.update');
        Route::post('billing/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('billing.subscription.cancel');
        Route::post('billing/subscription/resume', [SubscriptionController::class, 'resume'])->name('billing.subscription.resume');
        Route::patch('billing/profile', [BillingProfileController::class, 'update'])->name('billing.profile.update');
    });
});
