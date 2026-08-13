<?php

namespace App\Messaging\Providers;

use App\Messaging\Contracts\SmsProvider;
use App\Messaging\SentResult;
use App\Messaging\SmsMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Real SMS driver over Twilio's REST API (no SDK dependency — a plain
 * authenticated HTTP POST). Real code, but with no Twilio credentials in this
 * environment it is NOT exercised in tests — status "Implemented (untested —
 * requires credentials)", never "Tested" (ADR-0004, §38). Selected with
 * MARKETING_SMS_PROVIDER=twilio.
 */
class TwilioSmsProvider implements SmsProvider
{
    public function send(SmsMessage $message): SentResult
    {
        $sid = (string) config('marketing.twilio.account_sid');
        $token = (string) config('marketing.twilio.auth_token');
        $from = (string) config('marketing.twilio.from');

        if ($sid === '' || $token === '' || $from === '') {
            return SentResult::failed('Twilio is not configured (missing account SID, token or from number).');
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $message->toPhone,
                    'From' => $from,
                    'Body' => $message->body,
                ]);

            if ($response->failed()) {
                return SentResult::failed('Twilio error: '.$response->status());
            }

            return SentResult::accepted((string) $response->json('sid', 'twilio'));
        } catch (Throwable $e) {
            return SentResult::failed($e->getMessage());
        }
    }
}
