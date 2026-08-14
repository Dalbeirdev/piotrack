<?php

namespace App\Services\Seo;

/**
 * Generates schema.org JSON-LD from our own entity data (TSEO-011/012, AEO
 * schema types, LLMO-003). Pure + deterministic — no external service. Null
 * fields are omitted so the output is always valid.
 */
class SchemaGenerator
{
    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return ['Organization', 'LocalBusiness', 'Service', 'FAQPage', 'Article', 'Person', 'Review'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function generate(string $type, array $data): array
    {
        $base = ['@context' => 'https://schema.org', '@type' => $type];

        return match ($type) {
            'Organization' => $base + array_filter([
                'name' => $data['name'] ?? null,
                'url' => $data['url'] ?? null,
                'logo' => $data['logo'] ?? null,
                'telephone' => $data['phone'] ?? null,
                'sameAs' => $data['same_as'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'LocalBusiness' => $base + array_filter([
                'name' => $data['name'] ?? null,
                'telephone' => $data['phone'] ?? null,
                'url' => $data['url'] ?? null,
                'address' => $this->postalAddress($data),
            ], fn ($v) => $v !== null && $v !== '' && $v !== []),
            'Service' => $base + array_filter([
                'name' => $data['name'] ?? null,
                'serviceType' => $data['service_type'] ?? null,
                'provider' => isset($data['provider']) ? ['@type' => 'Organization', 'name' => $data['provider']] : null,
                'areaServed' => $data['area_served'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'FAQPage' => $base + [
                'mainEntity' => array_map(fn (array $q) => [
                    '@type' => 'Question',
                    'name' => $q['question'] ?? '',
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['answer'] ?? ''],
                ], $data['faqs'] ?? []),
            ],
            'Article' => $base + array_filter([
                'headline' => $data['headline'] ?? null,
                'author' => isset($data['author']) ? ['@type' => 'Person', 'name' => $data['author']] : null,
                'datePublished' => $data['date_published'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'Person' => $base + array_filter([
                'name' => $data['name'] ?? null,
                'jobTitle' => $data['job_title'] ?? null,
                'worksFor' => isset($data['works_for']) ? ['@type' => 'Organization', 'name' => $data['works_for']] : null,
            ], fn ($v) => $v !== null && $v !== ''),
            'Review' => $base + array_filter([
                'itemReviewed' => isset($data['item']) ? ['@type' => 'Thing', 'name' => $data['item']] : null,
                'reviewRating' => isset($data['rating']) ? ['@type' => 'Rating', 'ratingValue' => (string) $data['rating']] : null,
                'author' => isset($data['author']) ? ['@type' => 'Person', 'name' => $data['author']] : null,
            ], fn ($v) => $v !== null),
            default => $base,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function toJsonLd(string $type, array $data): string
    {
        return json_encode($this->generate($type, $data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function postalAddress(array $data): array
    {
        $fields = array_filter([
            'streetAddress' => $data['street'] ?? null,
            'addressLocality' => $data['city'] ?? null,
            'addressRegion' => $data['region'] ?? null,
            'postalCode' => $data['postal_code'] ?? null,
            'addressCountry' => $data['country'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $fields === [] ? [] : ['@type' => 'PostalAddress'] + $fields;
    }
}
