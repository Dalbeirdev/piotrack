<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Exceptions\AiCreditsExhaustedException;
use App\Ai\Exceptions\AiProviderException;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Contact;
use App\Models\Deal;
use App\Services\Ai\AiSalesAgent;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AiAgentController extends Controller
{
    public function __construct(private AiSalesAgent $agent) {}

    public function index(): Response
    {
        return Inertia::render('ai/agent', [
            'contacts' => Contact::latest('id')->limit(100)->get()->map(fn (Contact $c) => [
                'id' => $c->id,
                'name' => $c->fullName(),
                'lifecycle_stage' => $c->lifecycle_stage,
                'lead_score' => $c->lead_score,
            ]),
            'deals' => Deal::latest('id')->limit(50)->get()->map(fn (Deal $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'value' => $d->value,
            ]),
            'tasks' => ['qualify', 'research', 'next_action', 'draft_email', 'follow_up', 'score_lead'],
        ]);
    }

    /**
     * Run an informational agent task on a contact — output is returned to the
     * user, never acted upon.
     */
    public function run(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer', TenantExists::active('contacts')],
            'task' => ['required', Rule::in(['qualify', 'research', 'next_action', 'draft_email', 'follow_up', 'score_lead'])],
            'purpose' => ['nullable', 'string', 'max:200'],
        ]);

        $contact = Contact::findOrFail($data['contact_id']);

        try {
            $result = match ($data['task']) {
                'qualify' => $this->agent->qualify($contact),
                'research' => $this->agent->researchLead($contact),
                'next_action' => $this->agent->nextBestAction($contact),
                'draft_email' => $this->agent->draftEmail($contact, (string) ($data['purpose'] ?? 'introduce our managed services')),
                'follow_up' => $this->agent->followUp($contact),
                default => $this->agent->scoreLead($contact),
            };
        } catch (AiCreditsExhaustedException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        } catch (AiProviderException $e) {
            return back()->withErrors(['ai' => 'The AI provider failed: '.$e->getMessage()]);
        }

        return back()->with('ai_result', is_array($result) ? $result : ['text' => $result]);
    }

    public function objection(Request $request): RedirectResponse
    {
        $data = $request->validate(['objection' => ['required', 'string', 'max:1000']]);

        try {
            $response = $this->agent->handleObjection($data['objection']);
        } catch (AiCreditsExhaustedException|AiProviderException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('ai_result', ['text' => $response]);
    }

    public function conversations(): Response
    {
        return Inertia::render('ai/conversations', [
            'conversations' => AiConversation::with('contact:id,first_name,last_name')
                ->withCount('messages')->latest('id')->limit(50)->get()
                ->map(fn (AiConversation $c) => [
                    'id' => $c->id,
                    'contact' => $c->contact?->fullName(),
                    'channel' => $c->channel,
                    'status' => $c->status,
                    'summary' => $c->summary,
                    'messages_count' => $c->messages_count,
                    'messages' => $c->messages()->orderBy('id')->get()
                        ->map(fn (AiMessage $m) => ['id' => $m->id, 'role' => $m->role, 'body' => $m->body]),
                ]),
        ]);
    }

    public function startConversation(Request $request): RedirectResponse
    {
        $data = $request->validate(['contact_id' => ['nullable', 'integer', TenantExists::active('contacts')]]);

        AiConversation::create([
            'contact_id' => $data['contact_id'] ?? null,
            'channel' => 'web',
            'status' => 'open',
            'started_at' => now(),
        ]);

        return back()->with('status', __('Conversation started.'));
    }

    public function reply(Request $request, AiConversation $conversation): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        try {
            $this->agent->reply($conversation, $data['message']);
        } catch (AiCreditsExhaustedException|AiProviderException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('status', __('Reply generated.'));
    }

    public function summarize(AiConversation $conversation): RedirectResponse
    {
        try {
            $this->agent->summarizeConversation($conversation);
        } catch (AiCreditsExhaustedException|AiProviderException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('status', __('Conversation summarized.'));
    }

    /**
     * Propose a CRM update — recorded for human confirmation, never applied here.
     */
    public function proposeCrmUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer', TenantExists::active('contacts')],
            'changes' => ['required', 'array'],
        ]);

        /** @var array<string, mixed> $changes */
        $changes = $data['changes'];
        $this->agent->proposeCrmUpdate(Contact::findOrFail($data['contact_id']), $changes);

        return back()->with('status', __('Change proposed — it needs approval before it is applied.'));
    }
}
