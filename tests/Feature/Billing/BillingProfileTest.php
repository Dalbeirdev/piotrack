<?php

use App\Authorization\Role;
use App\Models\AuditLog;

it('saves billing profile details', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)
        ->patch(route('billing.profile.update'), [
            'billing_email' => 'ap@acme.example',
            'company_name' => 'Acme MSP LLC',
            'tax_id' => 'US-123456',
            'country' => 'US',
        ])
        ->assertRedirect();

    expect($org->billingProfile()->first())
        ->billing_email->toBe('ap@acme.example')
        ->company_name->toBe('Acme MSP LLC')
        ->tax_id->toBe('US-123456');

    expect(AuditLog::where('action', 'billing.profile_updated')->exists())->toBeTrue();
});

it('requires billing.manage to edit billing details', function () {
    [$org] = makeOrganization();
    $viewer = addMember($org, Role::Viewer);

    $this->actingAs($viewer)
        ->patch(route('billing.profile.update'), ['billing_email' => 'x@example.com'])
        ->assertForbidden();
});
