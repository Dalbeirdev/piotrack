<?php

declare(strict_types=1);

/**
 * QA §22 - the email chain's failure and repeat paths.
 *
 * CampaignTest already covers the happy send, tracking rows, suppression skips,
 * provider failures and the plan's monthly email limit. This adds the §22 asks
 * it does not: re-sending a campaign, hard bounces, and the unsubscribe round
 * trip where someone who opts out must be skipped by the NEXT campaign.
 *
 * Campaign sends produce CampaignRecipient rows, each with its own tracking
 * token; OutboundMessage is the automation/dispatcher path instead.
 *
 * Audience: Philadelphia CMMC prospects.
 */

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\MarketingList;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\ListService;
use App\Support\CurrentOrganization;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    [$this->org, $this->owner] = makeOrganization('Acme Managed IT Services');
    subscribeOrganization($this->org, 'enterprise');
    app(CurrentOrganization::class)->set($this->org);

    $this->list = MarketingList::create(['name' => 'Philadelphia CMMC prospects', 'type' => 'static']);

    $this->michael = Contact::create([
        'first_name' => 'Michael', 'last_name' => 'Rodriguez',
        'email' => 'michael.rodriguez@precisionmfg-test.com', 'email_opt_in' => true,
    ]);

    app(ListService::class)->addContact($this->list, $this->michael);
});

afterEach(fn () => app(CurrentOrganization::class)->forget());

function campaignFor(MarketingList $list, string $name = 'CMMC guide announcement'): Campaign
{
    return Campaign::create([
        'name' => $name,
        'channel' => 'email',
        'subject' => 'Your CMMC compliance checklist, {{first_name}}',
        'body_html' => '<p>Hello {{first_name}}, here is the checklist.</p>',
        'marketing_list_id' => $list->id,
        'status' => 'draft',
    ]);
}

it('refuses to send the same campaign twice', function () {
    $service = app(CampaignService::class);
    $campaign = campaignFor($this->list);

    $service->send($campaign);

    expect(CampaignRecipient::where('campaign_id', $campaign->id)->count())->toBe(1)
        ->and($campaign->fresh()->status)->toBe('sent');

    // A second send is refused outright rather than quietly duplicating.
    expect(fn () => $service->send($campaign->fresh()))->toThrow(ValidationException::class);

    expect(CampaignRecipient::where('campaign_id', $campaign->id)->count())->toBe(1);
});

it('refuses a campaign with no audience selected', function () {
    $campaign = Campaign::create([
        'name' => 'Orphan campaign', 'channel' => 'email',
        'subject' => 'Hello', 'body_html' => '<p>Body</p>', 'status' => 'draft',
    ]);

    expect(fn () => app(CampaignService::class)->send($campaign))->toThrow(ValidationException::class);
});

it('records a hard bounce without stopping the rest of the send', function () {
    // LogMailProvider treats a bounce+ address as a simulated hard bounce.
    $bouncing = Contact::create([
        'first_name' => 'Helena', 'last_name' => 'Vasquez',
        'email' => 'bounce+helena@precisionmfg-test.com', 'email_opt_in' => true,
    ]);
    app(ListService::class)->addContact($this->list, $bouncing);

    $campaign = campaignFor($this->list);
    app(CampaignService::class)->send($campaign);

    $good = CampaignRecipient::where('address', $this->michael->email)->first();
    $bad = CampaignRecipient::where('address', $bouncing->email)->first();

    expect($good)->not->toBeNull()
        ->and($bad)->not->toBeNull('the bouncing recipient was never attempted')
        // One failure must not abort the batch.
        ->and($good->status)->toBe('sent')
        ->and($bad->status)->toBe('failed')
        ->and($bad->error)->not->toBeEmpty();

    $campaign->refresh();

    expect($campaign->stat_recipients)->toBe(2)
        ->and($campaign->stat_sent)->toBe(1)
        ->and($campaign->stat_bounced)->toBe(1);
});

it('skips an unsubscribed recipient on the next campaign', function () {
    $service = app(CampaignService::class);

    $first = campaignFor($this->list, 'First touch');
    $service->send($first);

    $recipient = CampaignRecipient::where('address', $this->michael->email)->firstOrFail();
    expect($recipient->token)->not->toBeEmpty();

    app(CurrentOrganization::class)->forget();

    // Michael unsubscribes through the public tracking endpoint.
    $this->post('/e/u/'.$recipient->token)->assertSuccessful();

    app(CurrentOrganization::class)->set($this->org);

    expect(DB::table('suppressions')->where('address', $this->michael->email)->count())
        ->toBe(1, 'the unsubscribe did not add a suppression');

    // A second campaign to the same list must skip him entirely.
    $second = campaignFor($this->list, 'Second touch');
    $service->send($second);

    expect(CampaignRecipient::where('campaign_id', $second->id)->count())
        ->toBe(0, 'a suppressed recipient was emailed again')
        ->and($second->fresh()->stat_sent)->toBe(0);
});
