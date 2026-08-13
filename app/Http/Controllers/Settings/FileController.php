<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Support\AuditLogger;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tenant-scoped file storage (FILE-001). File records use BelongsToTenant, so
 * listing and route-model binding are automatically scoped to the current
 * organization. Uploads are validated (mime allowlist + size cap) and stored
 * under a tenant-prefixed path.
 */
class FileController extends Controller
{
    private const MAX_KB = 10240; // 10 MB

    private const ALLOWED_MIMES = 'pdf,jpg,jpeg,png,gif,webp,svg,csv,txt,doc,docx,xls,xlsx,ppt,pptx';

    public function __construct(
        private CurrentOrganization $currentOrganization,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('settings/files', [
            'files' => File::with('uploader:id,name')
                ->latest('id')
                ->get()
                ->map(fn (File $f) => [
                    'id' => $f->id,
                    'name' => $f->name,
                    'mime' => $f->mime,
                    'size' => $f->size,
                    'uploaded_by' => $f->uploader?->name,
                    'created_at' => $f->created_at,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_KB, 'mimes:'.self::ALLOWED_MIMES],
        ]);

        $organizationId = $this->currentOrganization->id();
        $upload = $request->file('file');
        $path = $upload->store("org-{$organizationId}/files", 'local');

        $file = File::create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'name' => $upload->getClientOriginalName(),
            'mime' => $upload->getClientMimeType(),
            'size' => $upload->getSize(),
        ]);

        $this->audit->log(
            'file.uploaded',
            context: ['name' => $file->name, 'size' => $file->size],
            resourceType: 'file',
            resourceId: (string) $file->id,
            organizationId: $organizationId,
        );

        return back()->with('status', __('File uploaded.'));
    }

    public function download(File $file): StreamedResponse
    {
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    public function destroy(File $file): RedirectResponse
    {
        Storage::disk($file->disk)->delete($file->path);

        $this->audit->log(
            'file.deleted',
            context: ['name' => $file->name],
            resourceType: 'file',
            resourceId: (string) $file->id,
            organizationId: $file->organization_id,
        );

        $file->delete();

        return back();
    }
}
