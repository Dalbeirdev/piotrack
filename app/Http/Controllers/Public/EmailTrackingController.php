<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Marketing\EmailTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Public email engagement endpoints (EMAIL-017…020). Records are resolved by
 * their unique token across tenants; the token is the capability. The open
 * pixel returns a 1×1 transparent GIF; the click endpoint validates the target
 * URL before redirecting (no open redirect).
 */
class EmailTrackingController extends Controller
{
    /** A 1×1 transparent GIF. */
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function __construct(private EmailTrackingService $tracking) {}

    public function open(string $token): Response
    {
        $this->tracking->open($token);

        return response((string) base64_decode(self::PIXEL, true), 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function click(Request $request, string $token): RedirectResponse
    {
        $this->tracking->click($token);

        $url = (string) $request->query('u', '');

        if ($url !== '' && preg_match('#^https?://#i', $url) === 1) {
            return redirect()->away($url);
        }

        return redirect('/');
    }

    public function unsubscribeShow(string $token): View
    {
        return view('public.unsubscribe', ['token' => $token]);
    }

    public function unsubscribe(string $token): View
    {
        $found = $this->tracking->unsubscribe($token);

        return view('public.message', [
            'title' => __('Unsubscribed'),
            'message' => $found
                ? __('You have been unsubscribed and will no longer receive these messages.')
                : __('This unsubscribe link was not recognized.'),
        ]);
    }
}
