<?php

namespace App\Messaging;

/**
 * A provider-agnostic email to send. Built by the dispatch pipeline after
 * personalization + tracking rewrites; the provider only performs transport.
 */
final class EmailMessage
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $toEmail,
        public string $subject,
        public string $html,
        public ?string $text = null,
        public ?string $fromEmail = null,
        public ?string $fromName = null,
        public array $headers = [],
    ) {}
}
