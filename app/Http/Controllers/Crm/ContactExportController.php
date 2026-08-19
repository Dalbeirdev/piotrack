<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Support\AuditLogger;
use App\Support\Csv;
use App\Support\CurrentOrganization;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of the current organization's contacts (IMEX-003). Streams so
 * large lists don't buffer in memory.
 */
class ContactExportController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function __invoke(): StreamedResponse
    {
        $this->audit->log('data.exported', context: ['resource' => 'contacts'], organizationId: $this->currentOrganization->id());

        $filename = 'contacts-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['First name', 'Last name', 'Email', 'Phone', 'Title', 'Company']);

            Contact::with('company:id,name')->chunk(500, function ($contacts) use ($out) {
                foreach ($contacts as $contact) {
                    // Csv::row neutralises formula injection: a contact created
                    // from an unauthenticated form could carry a value like
                    // "=HYPERLINK(...)" that would execute in a spreadsheet.
                    fputcsv($out, Csv::row([
                        $contact->first_name,
                        $contact->last_name,
                        $contact->email,
                        $contact->phone,
                        $contact->title,
                        $contact->company?->name,
                    ]));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
