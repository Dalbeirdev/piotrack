<?php

use App\Authorization\Role;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\DataRequest;
use App\Models\Organization;
use App\Models\PolicyAcceptance;
use App\Models\RetentionPolicy;
use App\Models\User;
use App\Services\Privacy\DataPrivacyService;
use App\Support\CurrentOrganization;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;

it('exports an organization to a file and records the request', function () {
    Storage::fake('local');
    [$org, $owner] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    Contact::create(['first_name' => 'Ann', 'last_name' => 'Prospect', 'email' => 'ann@x.com']);
    app(CurrentOrganization::class)->forget();

    $request = app(DataPrivacyService::class)->exportOrganization($org, $owner);

    expect($request->status)->toBe('completed')
        ->and($request->file_path)->not->toBeNull()
        ->and($request->completed_at)->not->toBeNull();

    Storage::disk('local')->assertExists($request->file_path);

    $payload = json_decode(Storage::disk('local')->get($request->file_path), true);
    expect($payload['organization']['name'])->toBe($org->name)
        ->and(collect($payload['contacts'])->pluck('email'))->toContain('ann@x.com')
        ->and(AuditLog::where('action', 'privacy.data.exported')->exists())->toBeTrue();
});

it('exports everything held about one person', function () {
    [, $owner] = makeOrganization();
    PolicyAcceptance::create(['user_id' => $owner->id, 'policy' => 'terms', 'version' => '2026-01', 'accepted_at' => now()]);

    $payload = app(DataPrivacyService::class)->userPayload($owner);

    expect($payload['user']['email'])->toBe($owner->email)
        ->and($payload['organizations'])->toHaveCount(1)
        ->and($payload['policy_acceptances'])->toHaveCount(1);
});

it('really deletes an organization and its tenant data', function () {
    [$org] = makeOrganization();
    [$other] = makeOrganization('Untouched');

    app(CurrentOrganization::class)->set($org);
    Contact::create(['first_name' => 'Gone', 'email' => 'gone@x.com']);
    app(CurrentOrganization::class)->set($other);
    Contact::create(['first_name' => 'Kept', 'email' => 'kept@x.com']);
    app(CurrentOrganization::class)->forget();

    $request = app(DataPrivacyService::class)->deleteOrganization($org);

    expect($request->status)->toBe('completed')
        // `withTrashed()` on purpose: Organization and Contact both soft-delete,
        // so a plain exists() would pass for a row that is merely flagged. An
        // erasure request must remove the row, not hide it.
        ->and(Organization::withTrashed()->whereKey($org->id)->exists())->toBeFalse()
        ->and(Contact::withoutGlobalScope('tenant')->withTrashed()->where('email', 'gone@x.com')->exists())->toBeFalse()
        // …and only that tenant's rows.
        ->and(Contact::withoutGlobalScope('tenant')->where('email', 'kept@x.com')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'privacy.organization.deleted')->exists())->toBeTrue();
});

it('refuses to delete a user who solely owns an organization', function () {
    [$org, $owner] = makeOrganization('Solely Owned');

    $result = app(DataPrivacyService::class)->deleteUser($owner);

    expect($result['deleted'])->toBeFalse()
        ->and($result['blocked_by'])->toContain('Solely Owned')
        // The account survives: deleting it would strand the organization.
        ->and(User::whereKey($owner->id)->exists())->toBeTrue();
});

it('deletes a user who owns nothing alone', function () {
    [$org] = makeOrganization();
    $member = addMember($org, Role::MarketingUser);

    $result = app(DataPrivacyService::class)->deleteUser($member);

    expect($result['deleted'])->toBeTrue()
        ->and(User::whereKey($member->id)->exists())->toBeFalse()
        ->and(Organization::whereKey($org->id)->exists())->toBeTrue();
});

it('records a policy acceptance once per version', function () {
    [, $owner] = makeOrganization();

    PolicyAcceptance::create(['user_id' => $owner->id, 'policy' => 'privacy', 'version' => 'v1', 'accepted_at' => now()]);

    expect(fn () => PolicyAcceptance::create(['user_id' => $owner->id, 'policy' => 'privacy', 'version' => 'v1', 'accepted_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);

    // A new version is a separate, recordable acceptance.
    PolicyAcceptance::create(['user_id' => $owner->id, 'policy' => 'privacy', 'version' => 'v2', 'accepted_at' => now()]);
    expect($owner->policyAcceptances()->count())->toBe(2);
});

it('prunes only records past an active retention window', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);

    RetentionPolicy::create(['subject' => 'audit_logs', 'retain_days' => 30, 'is_active' => true]);

    AuditLog::create(['organization_id' => $org->id, 'action' => 'old.event', 'created_at' => now()->subDays(60)]);
    AuditLog::create(['organization_id' => $org->id, 'action' => 'recent.event', 'created_at' => now()->subDays(5)]);
    app(CurrentOrganization::class)->forget();

    $this->artisan('privacy:prune-expired-data')->assertSuccessful();

    expect(AuditLog::where('action', 'old.event')->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'recent.event')->exists())->toBeTrue();
});

it('keeps data indefinitely when no retention rule is set', function () {
    [$org] = makeOrganization();
    AuditLog::create(['organization_id' => $org->id, 'action' => 'ancient.event', 'created_at' => now()->subYears(3)]);

    $this->artisan('privacy:prune-expired-data')->assertSuccessful();

    // Retention is opt-in: nothing is destroyed without an explicit rule.
    expect(AuditLog::where('action', 'ancient.event')->exists())->toBeTrue();
});

it('reports without deleting on a dry run', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    RetentionPolicy::create(['subject' => 'audit_logs', 'retain_days' => 1, 'is_active' => true]);
    app(CurrentOrganization::class)->forget();
    AuditLog::create(['organization_id' => $org->id, 'action' => 'stale.event', 'created_at' => now()->subDays(10)]);

    $this->artisan('privacy:prune-expired-data', ['--dry-run' => true])->assertSuccessful();

    expect(AuditLog::where('action', 'stale.event')->exists())->toBeTrue();
});

it('tracks a data request through its lifecycle', function () {
    Storage::fake('local');
    [$org, $owner] = makeOrganization();

    app(DataPrivacyService::class)->exportOrganization($org, $owner);

    $request = DataRequest::latest('id')->first();
    expect($request->type)->toBe('export')
        ->and($request->requested_by)->toBe($owner->id)
        ->and($request->status)->toBe('completed');
});
