<?php

namespace App\Marketing;

/**
 * Injects tracking into a rendered email body: a 1×1 open pixel, click-through
 * redirects on links, and an unsubscribe footer. Tracking lives on OUR
 * endpoints (see the public /e/* routes) so analytics do not depend on a
 * provider's webhooks (ADR-0004).
 */
class EmailBody
{
    public static function withTracking(string $html, string $token): string
    {
        $open = url("/e/o/{$token}");
        $unsub = url("/e/u/{$token}");
        $clickBase = url("/e/c/{$token}");

        // Rewrite outbound links through the click tracker.
        $html = preg_replace_callback(
            '/href="(https?:\/\/[^"]+)"/i',
            fn (array $m) => 'href="'.$clickBase.'?u='.urlencode($m[1]).'"',
            $html,
        ) ?? $html;

        return $html
            ."\n<img src=\"{$open}\" width=\"1\" height=\"1\" alt=\"\" style=\"display:none\">"
            ."\n<div style=\"font-size:12px;color:#888;margin-top:16px\">"
            ."<a href=\"{$unsub}\">Unsubscribe</a></div>";
    }
}
