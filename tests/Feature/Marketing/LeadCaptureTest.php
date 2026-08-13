<?php

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\MarketingList;
use App\Models\Organization;
use App\Models\Workflow;
use App\Models\WorkflowEnrollment;
use App\Support\CurrentOrganization;

/**
 * Create a published form (+ optional target list) in the given org.
 */
function makeForm(Organization $org, array $overrides = []): Form
{
    app(CurrentOrganization::class)->set($org);

    $form = Form::create(array_merge([
        'name' => 'Contact Us',
        'slug' => 'contact-us-'.$org->id,
        'fields' => [
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'first_name', 'label' => 'First name', 'type' => 'text', 'required' => false],
        ],
        'lifecycle_stage' => 'lead',
        'status' => 'published',
    ], $overrides));

    app(CurrentOrganization::class)->forget();

    return $form;
}

it('captures a public form submission into a tenant-scoped contact', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $list = MarketingList::create(['name' => 'Newsletter', 'type' => 'static']);
    app(CurrentOrganization::class)->forget();

    $form = makeForm($org, ['slug' => 'join', 'target_list_id' => $list->id]);

    $this->post('/f/join', ['email' => 'jane@example.com', 'first_name' => 'Jane'])->assertOk();

    $contact = Contact::withoutGlobalScope('tenant')->firstWhere('email', 'jane@example.com');
    expect($contact)->not->toBeNull()
        ->and($contact->organization_id)->toBe($org->id)
        ->and($contact->lifecycle_stage)->toBe('lead')
        ->and($contact->lead_source)->toBe('form');

    // Added to the target list, submission + audit recorded.
    expect($contact->lists()->withoutGlobalScope('tenant')->count())->toBe(1);
    expect(FormSubmission::withoutGlobalScope('tenant')->where('form_id', $form->id)->count())->toBe(1);
    expect(AuditLog::withoutGlobalScope('tenant')->where('action', 'lead.captured')->exists())->toBeTrue();
    expect($form->refresh()->submission_count)->toBe(1);
});

it('dedupes a repeat submission by email', function () {
    [$org] = makeOrganization();
    makeForm($org, ['slug' => 'dedupe']);

    $this->post('/f/dedupe', ['email' => 'dup@example.com', 'first_name' => 'A'])->assertOk();
    $this->post('/f/dedupe', ['email' => 'dup@example.com', 'first_name' => 'A'])->assertOk();

    expect(Contact::withoutGlobalScope('tenant')->where('email', 'dup@example.com')->count())->toBe(1);
});

it('silently drops a honeypot submission', function () {
    [$org] = makeOrganization();
    makeForm($org, ['slug' => 'hp']);

    $this->post('/f/hp', ['email' => 'bot@example.com', 'website' => 'http://spam'])->assertOk();

    expect(Contact::withoutGlobalScope('tenant')->where('email', 'bot@example.com')->exists())->toBeFalse();
});

it('validates required fields', function () {
    [$org] = makeOrganization();
    makeForm($org, ['slug' => 'req']);

    $this->post('/f/req', ['first_name' => 'NoEmail'])->assertSessionHasErrors('email');
});

it('404s an unpublished form', function () {
    [$org] = makeOrganization();
    makeForm($org, ['slug' => 'draft', 'status' => 'draft']);

    $this->get('/f/draft')->assertNotFound();
    $this->post('/f/draft', ['email' => 'x@example.com'])->assertNotFound();
});

it('enrolls the contact in a form-submission workflow', function () {
    [$org] = makeOrganization();
    app(CurrentOrganization::class)->set($org);
    $workflow = Workflow::create(['name' => 'Welcome', 'trigger_type' => 'form_submission', 'status' => 'active']);
    $workflow->steps()->create(['position' => 0, 'action_type' => 'send_email', 'action_config' => ['subject' => 'Hi', 'body' => 'Welcome {{first_name}}'], 'delay_minutes' => 0]);
    app(CurrentOrganization::class)->forget();

    makeForm($org, ['slug' => 'wf']);
    $this->post('/f/wf', ['email' => 'lead@example.com', 'first_name' => 'Lee'])->assertOk();

    $contact = Contact::withoutGlobalScope('tenant')->firstWhere('email', 'lead@example.com');
    expect(WorkflowEnrollment::withoutGlobalScope('tenant')
        ->where('workflow_id', $workflow->id)->where('contact_id', $contact->id)->where('status', 'active')->exists())
        ->toBeTrue();
    expect($workflow->refresh()->enrolled_count)->toBe(1);
});

it('resolves the tenant from the form slug across organizations', function () {
    [$orgA] = makeOrganization('A');
    [$orgB] = makeOrganization('B');
    makeForm($orgA, ['slug' => 'org-a-form']);
    makeForm($orgB, ['slug' => 'org-b-form']);

    $this->post('/f/org-b-form', ['email' => 'b@example.com'])->assertOk();

    $contact = Contact::withoutGlobalScope('tenant')->firstWhere('email', 'b@example.com');
    expect($contact->organization_id)->toBe($orgB->id);
});
