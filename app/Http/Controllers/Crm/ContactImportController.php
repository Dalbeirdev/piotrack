<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Services\ContactImporter;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactImportController extends Controller
{
    public function __construct(
        private CurrentOrganization $currentOrganization,
        private ContactImporter $importer,
    ) {}

    public function create(): Response
    {
        return Inertia::render('crm/contacts/import', [
            'history' => ImportJob::where('resource', 'contacts')
                ->latest('id')->limit(10)->get(['id', 'filename', 'imported', 'skipped', 'failed', 'created_at']),
            'preview' => session('import_preview'),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $parsed = $this->importer->parse($request->file('file')->getRealPath());
        $analysis = $this->importer->analyze($this->currentOrganization->get(), $parsed['rows']);

        return back()->with('import_preview', [
            'mapping' => $parsed['mapping'],
            'filename' => $request->file('file')->getClientOriginalName(),
            ...$analysis,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $parsed = $this->importer->parse($request->file('file')->getRealPath());
        $job = $this->importer->import(
            $this->currentOrganization->get(),
            $request->user(),
            $request->file('file')->getClientOriginalName(),
            $parsed['rows'],
        );

        return redirect()->route('crm.contacts.index')
            ->with('status', __(':imported imported, :skipped skipped, :failed failed.', [
                'imported' => $job->imported,
                'skipped' => $job->skipped,
                'failed' => $job->failed,
            ]));
    }
}
