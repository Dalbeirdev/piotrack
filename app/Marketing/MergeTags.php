<?php

namespace App\Marketing;

use App\Models\Contact;

/**
 * Renders `{{ tag }}` merge fields in subjects/bodies from a contact. Unknown
 * tags render empty — a template never leaks a raw `{{ ... }}` to a recipient.
 */
class MergeTags
{
    public static function render(string $content, Contact $contact): string
    {
        $company = $contact->company;

        $map = [
            'first_name' => (string) $contact->first_name,
            'last_name' => (string) $contact->last_name,
            'full_name' => $contact->fullName(),
            'email' => (string) $contact->email,
            'company' => $company !== null ? (string) $company->name : '',
        ];

        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $m) => $map[$m[1]] ?? '',
            $content,
        ) ?? $content;
    }
}
