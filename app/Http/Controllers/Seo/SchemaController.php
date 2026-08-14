<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\StructuredData;
use App\Services\Seo\SchemaGenerator;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SchemaController extends Controller
{
    public function __construct(
        private SchemaGenerator $schema,
        private AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('seo/schema/index', [
            'types' => SchemaGenerator::types(),
            'items' => StructuredData::latest('id')->get()->map(fn (StructuredData $s) => [
                'id' => $s->id,
                'schema_type' => $s->schema_type,
                'url' => $s->url,
                'jsonld' => $s->jsonld,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'schema_type' => ['required', Rule::in(SchemaGenerator::types())],
            'url' => ['nullable', 'url', 'max:2048'],
            'data' => ['required', 'array'],
        ]);

        $jsonld = $this->schema->toJsonLd($data['schema_type'], $data['data']);

        $item = StructuredData::create([
            'url' => $data['url'] ?? null,
            'schema_type' => $data['schema_type'],
            'jsonld' => $jsonld,
        ]);

        $this->audit->log('seo.schema.generated', context: ['type' => $item->schema_type], resourceType: 'structured_data', resourceId: (string) $item->id, organizationId: $item->organization_id);

        return back()->with('status', __(':type schema generated.', ['type' => $item->schema_type]));
    }

    public function destroy(StructuredData $schema): RedirectResponse
    {
        $schema->delete();

        return back()->with('status', __('Schema removed.'));
    }
}
