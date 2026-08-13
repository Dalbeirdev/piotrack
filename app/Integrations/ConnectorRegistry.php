<?php

namespace App\Integrations;

/**
 * The catalog of available connectors (INTG-001). Like the plan catalog, this
 * is the code source of truth. `auth_type` is none|api_key|oauth; `connectable`
 * marks whether a connector can be connected in this environment — OAuth
 * connectors require a registered OAuth app + credentials and are surfaced as
 * "coming soon" until those exist (INTG-004…010 remain Planned).
 */
class ConnectorRegistry
{
    /**
     * @return list<array{key: string, name: string, category: string, auth_type: string, connectable: bool}>
     */
    public static function all(): array
    {
        return [
            ['key' => 'demo_source', 'name' => 'Demo Data Source', 'category' => 'Demo', 'auth_type' => 'api_key', 'connectable' => true],
            ['key' => 'stripe', 'name' => 'Stripe', 'category' => 'Billing', 'auth_type' => 'api_key', 'connectable' => true],
            ['key' => 'mailchimp', 'name' => 'Mailchimp', 'category' => 'Email', 'auth_type' => 'api_key', 'connectable' => true],
            ['key' => 'hubspot', 'name' => 'HubSpot', 'category' => 'CRM', 'auth_type' => 'api_key', 'connectable' => true],
            ['key' => 'google_analytics', 'name' => 'Google Analytics', 'category' => 'Analytics', 'auth_type' => 'oauth', 'connectable' => false],
            ['key' => 'google_ads', 'name' => 'Google Ads', 'category' => 'Advertising', 'auth_type' => 'oauth', 'connectable' => false],
            ['key' => 'linkedin_ads', 'name' => 'LinkedIn Ads', 'category' => 'Advertising', 'auth_type' => 'oauth', 'connectable' => false],
            ['key' => 'slack', 'name' => 'Slack', 'category' => 'Communication', 'auth_type' => 'oauth', 'connectable' => false],
        ];
    }

    /**
     * @return array{key: string, name: string, category: string, auth_type: string, connectable: bool}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $connector) {
            if ($connector['key'] === $key) {
                return $connector;
            }
        }

        return null;
    }

    public static function isConnectable(string $key): bool
    {
        return (bool) (self::find($key)['connectable'] ?? false);
    }

    public static function name(string $key): string
    {
        return self::find($key)['name'] ?? $key;
    }
}
