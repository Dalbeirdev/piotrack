<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiPromptTemplate;
use App\Services\Ai\PromptRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiPromptTemplateController extends Controller
{
    public function __construct(private PromptRegistry $prompts) {}

    public function index(): Response
    {
        return Inertia::render('ai/prompts', [
            'templates' => AiPromptTemplate::orderBy('key')->orderByDesc('version')->get()
                ->map(fn (AiPromptTemplate $t) => [
                    'id' => $t->id,
                    'key' => $t->key,
                    'version' => $t->version,
                    'description' => $t->description,
                    'system' => $t->system,
                    'template' => $t->template,
                    'is_active' => $t->is_active,
                ]),
            'known_keys' => $this->prompts->knownKeys(),
        ]);
    }

    /**
     * Publishing creates a new version; the previous one is preserved.
     */
    public function publish(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'template' => ['required', 'string', 'max:8000'],
            'system' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:200'],
        ]);

        $this->prompts->publish($data['key'], $data['template'], $data['system'] ?? null, $data['description'] ?? null);

        return back()->with('status', __('New prompt version published.'));
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100'],
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $this->prompts->activate($data['key'], (int) $data['version']);

        return back()->with('status', __('Prompt version activated.'));
    }
}
