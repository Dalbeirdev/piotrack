<?php

use App\Billing\Limit;
use App\Billing\UsageMeter;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\MarketingList;
use App\Models\Organization;
use App\Models\Suppression;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\ListService;
use App\Support\CurrentOrganization;

/**
 * Build a list with members in the given org and return [list, contacts].
 *
 * @return array{0: MarketingList, 1: array<string, Contact>}
 */
function listWithContacts(Organization $org): array
{
    app(CurrentOrganization::class)->set($org);
    $list = MarketingList::create(['name' => 'Audience', 'type' => 'static']);

    $contacts = [
        'a' => Contact::create(['first_name' => 'Ann', 'email' => 'ann@example.com', 'email_opt_in' => true]),
        'b' => Contact::create(['first_name' => 'Bob', 'email' => 'bob@example.com', 'email_opt_in' => true]),
        'optout' => Contact::create(['first_name' => 'Opt', 'email' => 'opt@example.com', 'email_opt_in' => false]),
    ];

    $svc = app(ListService::class);
    foreach ($contacts as $c) {
        $svc->addContact($list, $c);
    }
    app(CurrentOrganization::class)->forget();

    return [$list, $contacts];
}

it('sends an email campaign to opted-in list members and records tracking rows', function () {
    [$org, $owner] = makeOrganization();
    [$list] = listWithContacts($org);

    app(CurrentOrganization::class)->set($org);
    $campaign = Campaign::create([
        'name' => 'Launch', 'channel' => 'email', 'subject' => 'Hello {{first_name}}',
        'body_html' => '<p>Hi {{first_name}}, see <a href="https://piotrack.com">this</a>.</p>',
        'marketing_list_id' => $list->id, 'status' => 'draft',
    ]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('marketing.campaigns.send', $campaign->id))->assertRedirect();

    $campaign->refresh();
    expect($campaign->status)->toBe('sent')
        ->and($campaign->stat_recipients)->toBe(3) // list size
        ->and($campaign->stat_sent)->toBe(2);      // opted-out skipped

    $recipients = CampaignRecipient::withoutGlobalScope('tenant')->where('campaign_id', $campaign->id)->get();
    expect($recipients)->toHaveCount(2);
    expect($recipients->every(fn ($r) => $r->status === 'sent'))->toBeTrue();
});

it('records opens, clicks and unsubscribes from the public tracking endpoints', function () {
    [$org, $owner] = makeOrganization();
    [$list] = listWithContacts($org);

    app(CurrentOrganization::class)->set($org);
    $campaign = Campaign::create(['name' => 'Track', 'channel' => 'email', 'subject' => 'S', 'body_html' => '<a href="https://x.test">x</a>', 'marketing_list_id' => $list->id]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('marketing.campaigns.send', $campaign->id))->assertRedirect();

    $recipient = CampaignRecipient::withoutGlobalScope('tenant')->where('campaign_id', $campaign->id)->first();

    // Open pixel.
    $this->get("/e/o/{$recipient->token}")->assertOk()->assertHeader('Content-Type', 'image/gif');
    expect($recipient->refresh()->opened_at)->not->toBeNull();
    expect($campaign->refresh()->stat_opened)->toBe(1);

    // Click redirect (valid URL).
    $this->get("/e/c/{$recipient->token}?u=".urlencode('https://x.test'))->assertRedirect('https://x.test');
    expect($recipient->refresh()->clicked_at)->not->toBeNull();
    expect($campaign->refresh()->stat_clicked)->toBe(1);

    // Unsubscribe creates a suppression and blocks future sends.
    $this->post("/e/u/{$recipient->token}")->assertOk();
    expect(Suppression::withoutGlobalScope('tenant')->where('address', $recipient->address)->where('channel', 'email')->exists())->toBeTrue();
    expect($campaign->refresh()->stat_unsubscribed)->toBe(1);
});

it('skips suppressed addresses on send', function () {
    [$org, $owner] = makeOrganization();
    [$list, $contacts] = listWithContacts($org);

    app(CurrentOrganization::class)->set($org);
    Suppression::create(['channel' => 'email', 'address' => $contacts['a']->email, 'reason' => 'unsubscribe']);
    $campaign = Campaign::create(['name' => 'Skip', 'channel' => 'email', 'subject' => 'S', 'body_html' => 'x', 'marketing_list_id' => $list->id]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('marketing.campaigns.send', $campaign->id))->assertRedirect();

    // Only Bob (opted-in, not suppressed) receives it; Ann suppressed, Opt opted-out.
    expect($campaign->refresh()->stat_sent)->toBe(1);
    expect(CampaignRecipient::withoutGlobalScope('tenant')->where('campaign_id', $campaign->id)->where('status', 'sent')->pluck('address')->all())
        ->toBe(['bob@example.com']);
});

it('marks provider failures without stopping the send', function () {
    [$org, $owner] = makeOrganization();

    app(CurrentOrganization::class)->set($org);
    $list = MarketingList::create(['name' => 'Mixed', 'type' => 'static']);
    $svc = app(ListService::class);
    $svc->addContact($list, Contact::create(['first_name' => 'Good', 'email' => 'good@example.com', 'email_opt_in' => true]));
    // LogMailProvider fails addresses starting with "bounce+".
    $svc->addContact($list, Contact::create(['first_name' => 'Bad', 'email' => 'bounce+x@example.com', 'email_opt_in' => true]));
    $campaign = Campaign::create(['name' => 'Partial', 'channel' => 'email', 'subject' => 'S', 'body_html' => 'x', 'marketing_list_id' => $list->id]);
    app(CurrentOrganization::class)->forget();

    app(CurrentOrganization::class)->set($org);
    app(CampaignService::class)->send($campaign);
    app(CurrentOrganization::class)->forget();

    $campaign->refresh();
    expect($campaign->stat_sent)->toBe(1)->and($campaign->stat_bounced)->toBe(1);
});

it('blocks a send that would exceed the monthly email limit', function () {
    [$org, $owner] = makeOrganization(); // Growth trial: 25000 email limit
    [$list] = listWithContacts($org);

    // Consume the entire allowance.
    app(UsageMeter::class)->increment($org, Limit::Emails, 25000);

    app(CurrentOrganization::class)->set($org);
    $campaign = Campaign::create(['name' => 'OverLimit', 'channel' => 'email', 'subject' => 'S', 'body_html' => 'x', 'marketing_list_id' => $list->id]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('marketing.campaigns.send', $campaign->id))->assertSessionHasErrors('audience');
    expect($campaign->refresh()->status)->not->toBe('sent');
});

it('sends an SMS campaign only to sms-opted-in contacts', function () {
    [$org, $owner] = makeOrganization();

    app(CurrentOrganization::class)->set($org);
    $list = MarketingList::create(['name' => 'SMS', 'type' => 'static']);
    $svc = app(ListService::class);
    $svc->addContact($list, Contact::create(['first_name' => 'Yes', 'phone' => '+15551110000', 'sms_opt_in' => true]));
    $svc->addContact($list, Contact::create(['first_name' => 'No', 'phone' => '+15551110001', 'sms_opt_in' => false]));
    $campaign = Campaign::create(['name' => 'Blast', 'channel' => 'sms', 'body_text' => 'Hi {{first_name}}', 'marketing_list_id' => $list->id]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($owner)->post(route('marketing.campaigns.send', $campaign->id))->assertRedirect();
    expect($campaign->refresh()->stat_sent)->toBe(1);
});
