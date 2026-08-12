<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Browser session revocation (AUTH-006): logs the user out everywhere
 * except the current browser.
 */
class SessionController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function destroyOthers(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();

        Auth::logoutOtherDevices($validated['password']);

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $this->audit->log('auth.other_sessions_revoked');

        return back();
    }
}
