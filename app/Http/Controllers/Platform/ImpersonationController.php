<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Platform\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ImpersonationController extends Controller
{
    public function __construct(private ImpersonationService $impersonation) {}

    /**
     * Start a support impersonation session. Gated by `admin.impersonate`; the
     * service enforces the rest (reason required, platform staff untouchable).
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);

        $actor = $request->user();
        abort_if($actor === null, 403);

        try {
            $this->impersonation->start($actor, $user, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['impersonation' => $e->getMessage()]);
        }

        return redirect()->route('dashboard')
            ->with('status', __('You are now impersonating :name.', ['name' => $user->name]));
    }

    /**
     * End impersonation and return to the original account. Deliberately
     * requires no permission: anyone inside an impersonated session must always
     * be able to get out of it.
     */
    public function stop(): RedirectResponse
    {
        $this->impersonation->stop();

        return redirect()->route('platform.dashboard')->with('status', __('Impersonation ended.'));
    }
}
