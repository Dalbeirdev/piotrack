<?php

use App\Models\StructuredData;
use App\Services\Seo\ContentReadinessScorer;
use App\Services\Seo\SchemaGenerator;

it('generates Organization JSON-LD', function () {
    $schema = (new SchemaGenerator)->generate('Organization', ['name' => 'Acme MSP', 'url' => 'https://acme.test', 'phone' => '+12145551000']);

    expect($schema['@context'])->toBe('https://schema.org')
        ->and($schema['@type'])->toBe('Organization')
        ->and($schema['name'])->toBe('Acme MSP')
        ->and($schema['telephone'])->toBe('+12145551000')
        ->and($schema)->not->toHaveKey('logo'); // null omitted
});

it('generates a FAQPage with questions', function () {
    $schema = (new SchemaGenerator)->generate('FAQPage', ['faqs' => [['question' => 'What is an MSP?', 'answer' => 'A managed service provider.']]]);

    expect($schema['mainEntity'][0]['@type'])->toBe('Question')
        ->and($schema['mainEntity'][0]['name'])->toBe('What is an MSP?')
        ->and($schema['mainEntity'][0]['acceptedAnswer']['text'])->toBe('A managed service provider.');
});

it('generates LocalBusiness with a nested postal address', function () {
    $schema = (new SchemaGenerator)->generate('LocalBusiness', ['name' => 'Acme', 'street' => '1 Main St', 'city' => 'Dallas', 'region' => 'TX']);

    expect($schema['address']['@type'])->toBe('PostalAddress')
        ->and($schema['address']['addressLocality'])->toBe('Dallas')
        ->and($schema['address']['addressRegion'])->toBe('TX');
});

it('saves generated schema via the controller', function () {
    [$org, $owner] = makeOrganization();

    $this->actingAs($owner)->post(route('seo.schema.store'), [
        'schema_type' => 'Organization',
        'data' => ['name' => 'Acme MSP'],
    ])->assertRedirect();

    $item = StructuredData::withoutGlobalScope('tenant')->firstWhere('schema_type', 'Organization');
    expect($item)->not->toBeNull()
        ->and($item->jsonld)->toContain('Acme MSP')
        ->and($item->jsonld)->toContain('schema.org');
});

it('scores machine-readable content higher than poor content', function () {
    $scorer = new ContentReadinessScorer;

    $good = $scorer->score('<html><body><main><article><h1>Title</h1><h2>Section</h2><ul><li>a</li></ul>'
        .'<script type="application/ld+json">{}</script><p>'.str_repeat('word ', 350).'</p></article></main></body></html>');
    $poor = $scorer->score('<html><body><div>hello world</div></body></html>');

    expect($good['score'])->toBeGreaterThan($poor['score'])
        ->and($good['score'])->toBeGreaterThan(80)
        ->and($poor['score'])->toBeLessThan(40);
});
