<?php

namespace App\Services\Seo;

use App\Models\SeoLocation;

/**
 * Checks a citation's listed Name/Address/Phone against the location's canonical
 * NAP (LSEO-012). Normalizes case, punctuation, whitespace, common company
 * suffixes and phone formatting before comparing, so cosmetic differences don't
 * flag but real inconsistencies do.
 */
class NapConsistencyChecker
{
    /**
     * @param  array{name?: ?string, address?: ?string, phone?: ?string}  $listed
     * @return array{status: string, mismatches: list<string>}
     */
    public function check(SeoLocation $location, array $listed): array
    {
        $name = (string) ($listed['name'] ?? '');
        $address = (string) ($listed['address'] ?? '');
        $phone = (string) ($listed['phone'] ?? '');

        if ($name === '' && $address === '' && $phone === '') {
            return ['status' => 'missing', 'mismatches' => ['name', 'address', 'phone']];
        }

        $mismatches = [];

        if ($this->normalizeName($location->name) !== $this->normalizeName($name)) {
            $mismatches[] = 'name';
        }

        $canonicalAddress = implode(' ', array_filter([
            $location->street, $location->city, $location->region, $location->postal_code,
        ]));
        if ($this->normalizeText($canonicalAddress) !== $this->normalizeText($address)) {
            $mismatches[] = 'address';
        }

        if ($this->normalizePhone((string) $location->phone) !== $this->normalizePhone($phone)) {
            $mismatches[] = 'phone';
        }

        return ['status' => $mismatches === [] ? 'consistent' : 'inconsistent', 'mismatches' => $mismatches];
    }

    private function normalizeName(string $value): string
    {
        $value = $this->normalizeText($value);

        // Drop common company suffixes so "Acme LLC" == "Acme".
        return trim(preg_replace('/\b(llc|inc|incorporated|ltd|co|corp|corporation)\b/', '', $value) ?? $value);
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9 ]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
