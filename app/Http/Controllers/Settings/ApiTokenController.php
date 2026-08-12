<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal API tokens (AUTH-011). The plaintext token is flashed to the
 * session and shown exactly once; only a hash is stored (Sanctum).
 */
class ApiTokenController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/api-tokens', [
            'tokens' => $user->tokens()
                ->latest()
                ->get(['id', 'name', 'last_used_at', 'created_at']),
            'plainTextToken' => $request->session()->get('plainTextToken'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $token = $user->createToken($validated['name']);

        $this->audit->log(
            'auth.api_token_created',
            context: ['name' => $validated['name']],
            resourceType: 'personal_access_token',
            resourceId: (string) $token->accessToken->id,
        );

        return back()->with('plainTextToken', $token->plainTextToken);
    }

    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $token = $user->tokens()->whereKey($tokenId)->firstOrFail();
        $name = $token->name;
        $token->delete();

        $this->audit->log(
            'auth.api_token_revoked',
            context: ['name' => $name],
            resourceType: 'personal_access_token',
            resourceId: (string) $tokenId,
        );

        return back();
    }
}
