<?php

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

    // Teams.
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

    // Audit log viewer.
    Route::get('settings/audit-log', [AuditLogController::class, 'index'])
        ->middleware('can:audit.view')->name('audit.index');
});
