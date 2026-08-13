<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Models\ImportJob;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Contact CSV import pipeline (IMEX-001/002): header mapping, validation,
 * duplicate detection, preview, and committed import with a history record.
 */
class ContactImporter
{
    /** Header aliases → contact field. */
    private const FIELD_ALIASES = [
        'first_name' => ['first name', 'firstname', 'first', 'given name'],
        'last_name' => ['last name', 'lastname', 'last', 'surname', 'family name'],
        'email' => ['email', 'email address', 'e-mail'],
        'phone' => ['phone', 'phone number', 'mobile', 'telephone'],
        'title' => ['title', 'job title', 'role', 'position'],
        'company' => ['company', 'company name', 'organization', 'account'],
    ];

    /**
     * Parse a CSV file into a header list and mapped rows.
     *
     * @return array{headers: list<string>, mapping: array<string,string>, rows: list<array<string,string>>}
     */
    public function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle) ?: [];
        $mapping = $this->mapHeaders($headers);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($headers as $i => $header) {
                $field = $mapping[$header] ?? null;
                if ($field !== null) {
                    $row[$field] = trim((string) ($line[$i] ?? ''));
                }
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return ['headers' => $headers, 'mapping' => $mapping, 'rows' => $rows];
    }

    /**
     * Validate + classify rows without importing (preview).
     *
     * @param  list<array<string,string>>  $rows
     * @return array{total: int, valid: int, invalid: int, duplicates: int, sample: list<array{name: string, email: string, status: string, error: ?string}>}
     */
    public function analyze(Organization $organization, array $rows): array
    {
        /** @var array<string, bool> $existingEmails */
        $existingEmails = Contact::where('organization_id', $organization->id)
            ->whereNotNull('email')->pluck('email')
            ->mapWithKeys(fn ($e) => [Str::lower((string) $e) => true])->all();

        $seen = [];
        $valid = $invalid = $duplicates = 0;
        $sample = [];

        foreach ($rows as $i => $row) {
            [$status, $error] = $this->classify($row, $existingEmails, $seen);

            match ($status) {
                'valid' => $valid++,
                'duplicate' => $duplicates++,
                default => $invalid++,
            };

            if ($i < 10) {
                $sample[] = [
                    'name' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')),
                    'email' => $row['email'] ?? '',
                    'status' => $status,
                    'error' => $error,
                ];
            }
        }

        return ['total' => count($rows), 'valid' => $valid, 'invalid' => $invalid, 'duplicates' => $duplicates, 'sample' => $sample];
    }

    /**
     * Import valid rows and record the job (history + error report).
     *
     * @param  list<array<string,string>>  $rows
     */
    public function import(Organization $organization, ?User $user, string $filename, array $rows): ImportJob
    {
        return DB::transaction(function () use ($organization, $user, $filename, $rows) {
            /** @var array<string, bool> $existingEmails */
            $existingEmails = Contact::where('organization_id', $organization->id)
                ->whereNotNull('email')->pluck('email')
                ->mapWithKeys(fn ($e) => [Str::lower((string) $e) => true])->all();

            $seen = [];
            $imported = $skipped = $failed = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                [$status, $error] = $this->classify($row, $existingEmails, $seen);

                if ($status === 'duplicate') {
                    $skipped++;

                    continue;
                }
                if ($status === 'invalid') {
                    $failed++;
                    $errors[] = ['row' => $index + 2, 'error' => $error];

                    continue;
                }

                $companyId = null;
                if (! empty($row['company'])) {
                    $companyId = Company::firstOrCreate(
                        ['organization_id' => $organization->id, 'name' => $row['company']],
                    )->id;
                }

                Contact::create([
                    'organization_id' => $organization->id,
                    'company_id' => $companyId,
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'title' => $row['title'] ?? null,
                    'owner_id' => $user?->id,
                ]);

                if (! empty($row['email'])) {
                    $existingEmails[Str::lower($row['email'])] = true;
                }
                $imported++;
            }

            return ImportJob::create([
                'organization_id' => $organization->id,
                'user_id' => $user?->id,
                'resource' => 'contacts',
                'filename' => $filename,
                'status' => 'completed',
                'total' => count($rows),
                'imported' => $imported,
                'skipped' => $skipped,
                'failed' => $failed,
                'errors' => $errors === [] ? null : $errors,
            ]);
        });
    }

    /**
     * @param  array<string,string>  $row
     * @param  array<string,bool>  $existingEmails
     * @param  array<string,bool>  $seen
     * @return array{0: string, 1: ?string}
     */
    private function classify(array $row, array $existingEmails, array &$seen): array
    {
        if (empty($row['first_name'])) {
            return ['invalid', 'Missing first name'];
        }

        $email = $row['email'] ?? '';
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['invalid', 'Invalid email'];
        }

        if ($email !== '') {
            $key = Str::lower($email);
            if (isset($existingEmails[$key]) || isset($seen[$key])) {
                return ['duplicate', 'Duplicate email'];
            }
            $seen[$key] = true;
        }

        return ['valid', null];
    }

    /**
     * @param  list<string>  $headers
     * @return array<string,string>
     */
    private function mapHeaders(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $header) {
            $normalized = Str::lower(trim($header));
            foreach (self::FIELD_ALIASES as $field => $aliases) {
                if (in_array($normalized, $aliases, true)) {
                    $mapping[$header] = $field;
                    break;
                }
            }
        }

        return $mapping;
    }
}
