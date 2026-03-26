<?php

class ZohoIntegration {

    /**
     * Validate endpoint URL to prevent SSRF.
     */
    private static function validateEndpoint(string $endpoint): bool {
        $parsed = parse_url($endpoint);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) return false;
        if ($parsed['scheme'] !== 'https') return false;
        // Only allow zohoapis.com domains
        $host = $parsed['host'];
        if (!preg_match('/^(www\.)?zohoapis\.(com|eu|in|com\.au|jp|com\.cn)$/', $host)) {
            return false;
        }
        return true;
    }

    /**
     * Test the Zoho connection by calling the organization endpoint.
     * Supports Zoho Books, CRM, and Invoice APIs.
     */
    public static function test(array $config): array {
        $apiKey    = $config['apiKey'] ?? '';    // OAuth access token or API key
        $apiSecret = $config['apiSecret'] ?? ''; // Client secret (for reference)
        $endpoint  = rtrim($config['endpoint'] ?? 'https://www.zohoapis.com', '/');

        if (!$apiKey) {
            return [false, 'API Key / Access Token is required.'];
        }

        if (!self::validateEndpoint($endpoint)) {
            return [false, 'Invalid endpoint URL. Must be a valid Zoho API domain (zohoapis.com).'];
        }

        // Try Zoho CRM org endpoint first
        $url = "$endpoint/crm/v2/org";
        $result = self::httpGet($url, $apiKey);

        if (isset($result['data']) || isset($result['org'])) {
            $orgName = $result['data'][0]['company_name'] ?? $result['org'][0]['company_name'] ?? 'Unknown';
            return [true, "Connected to Zoho ($orgName)."];
        }

        // Try Zoho Books organization endpoint as fallback
        $url = "$endpoint/books/v3/organizations";
        $result = self::httpGet($url, $apiKey);

        if (isset($result['organizations'])) {
            $orgName = $result['organizations'][0]['name'] ?? 'Unknown';
            return [true, "Connected to Zoho Books ($orgName)."];
        }

        $errorMsg = $result['message'] ?? $result['error'] ?? 'Connection failed';
        return [false, "Zoho: $errorMsg"];
    }

    /**
     * Fetch contacts from Zoho CRM.
     */
    public static function getContacts(array $config): array {
        $apiKey   = $config['apiKey'] ?? '';
        $endpoint = rtrim($config['endpoint'] ?? 'https://www.zohoapis.com', '/');

        $url = "$endpoint/crm/v2/Contacts?per_page=200";
        $result = self::httpGet($url, $apiKey);

        return $result['data'] ?? [];
    }

    // ── HTTP helpers ────────────────────────────────────────

    private static function httpGet(string $url, string $token): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Zoho-oauthtoken $token",
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            return ['error' => "HTTP $code", 'message' => $body];
        }

        return json_decode($body ?: '{}', true) ?: [];
    }
}
