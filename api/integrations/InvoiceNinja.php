<?php

class InvoiceNinjaIntegration {

    /**
     * Validate endpoint URL to prevent SSRF — must be HTTPS with a public hostname.
     */
    private static function validateEndpoint(string $endpoint): bool {
        $parsed = parse_url($endpoint);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) return false;
        if ($parsed['scheme'] !== 'https') return false;
        // Block private/internal IPs
        $ip = gethostbyname($parsed['host']);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        return true;
    }

    /**
     * Test the Invoice Ninja connection by calling the /api/v1/ping endpoint.
     */
    public static function test(array $config): array {
        $apiKey   = $config['apiKey'] ?? '';
        $endpoint = rtrim($config['endpoint'] ?? 'https://app.invoiceninja.com', '/');

        if (!$apiKey) {
            return [false, 'API Key is required.'];
        }

        if (!self::validateEndpoint($endpoint)) {
            return [false, 'Invalid endpoint URL. Must be HTTPS with a public hostname.'];
        }

        // Invoice Ninja v5 uses /api/v1/ping for health checks
        $url = "$endpoint/api/v1/ping";
        $result = self::httpGet($url, $apiKey);

        // v5 returns something like "pong" or a JSON object
        if (isset($result['error'])) {
            return [false, 'Connection failed: ' . ($result['error'] ?? 'Unknown error')];
        }

        return [true, 'Connected to Invoice Ninja.'];
    }

    /**
     * Fetch clients from Invoice Ninja.
     */
    public static function getClients(array $config): array {
        $apiKey   = $config['apiKey'] ?? '';
        $endpoint = rtrim($config['endpoint'] ?? 'https://app.invoiceninja.com', '/');

        $url = "$endpoint/api/v1/clients?per_page=100&status=active";
        $result = self::httpGet($url, $apiKey);

        return $result['data'] ?? [];
    }

    /**
     * Create an invoice in Invoice Ninja.
     */
    public static function createInvoice(array $config, array $data): array {
        $apiKey   = $config['apiKey'] ?? '';
        $endpoint = rtrim($config['endpoint'] ?? 'https://app.invoiceninja.com', '/');

        $url = "$endpoint/api/v1/invoices";
        return self::httpPost($url, $apiKey, $data);
    }

    // ── HTTP helpers ────────────────────────────────────────

    private static function httpGet(string $url, string $apiKey): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "X-Api-Token: $apiKey",
                'Accept: application/json',
                'X-Requested-With: XMLHttpRequest',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            return ['error' => "HTTP $code: $body"];
        }

        $decoded = json_decode($body ?: '{}', true);
        // Invoice Ninja ping returns plain text "pong" sometimes
        if ($decoded === null && stripos($body, 'pong') !== false) {
            return ['status' => 'ok'];
        }

        return $decoded ?: [];
    }

    private static function httpPost(string $url, string $apiKey, array $data): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                "X-Api-Token: $apiKey",
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Requested-With: XMLHttpRequest',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return json_decode($body ?: '{}', true) ?: [];
    }
}
