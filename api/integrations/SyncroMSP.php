<?php

class SyncroMSPIntegration {

    /**
     * Validate subdomain is alphanumeric to prevent SSRF/injection.
     */
    private static function validateSubdomain(string $subdomain): bool {
        return (bool) preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/', $subdomain);
    }

    public static function test(array $config): array {
        $apiKey    = $config['apiKey'] ?? '';
        $subdomain = $config['subdomain'] ?? '';

        if (!$apiKey || !$subdomain) {
            return [false, 'API Key and Subdomain are required.'];
        }

        if (!self::validateSubdomain($subdomain)) {
            return [false, 'Invalid subdomain format.'];
        }

        $url = "https://{$subdomain}.syncromsp.com/api/v1/customers?page=1";
        $result = self::httpGet($url, $apiKey);

        if (isset($result['error']) || isset($result['message'])) {
            return [false, 'Connection failed: ' . ($result['error'] ?? $result['message'] ?? 'Unknown error')];
        }

        $count = $result['meta']['total_entries'] ?? count($result['customers'] ?? []);
        return [true, "Connected. Found $count customers."];
    }

    public static function getCustomers(array $config): array {
        $apiKey    = $config['apiKey'] ?? '';
        $subdomain = $config['subdomain'] ?? '';

        if (!$apiKey || !$subdomain) {
            Response::error('Syncro configuration missing. Set API Key and Subdomain in Admin > Syncro MSP.', 400);
        }

        if (!self::validateSubdomain($subdomain)) {
            Response::error('Invalid Syncro subdomain format.', 400);
        }

        $url = "https://{$subdomain}.syncromsp.com/api/v1/customers?page=1";
        $result = self::httpGet($url, $apiKey);

        if (!isset($result['customers'])) {
            Response::error('Failed to fetch customers from Syncro: ' . json_encode($result), 500);
        }

        return array_map(function ($c) {
            return [
                'id'       => (string) $c['id'],
                'name'     => trim(($c['firstname'] ?? '') . ' ' . ($c['lastname'] ?? '')),
                'company'  => $c['business_name'] ?? trim(($c['firstname'] ?? '') . ' ' . ($c['lastname'] ?? '')),
                'email'    => $c['email'] ?? '',
                'phone'    => $c['phone'] ?? '',
                'address'  => $c['address'] ?? '',
                'syncroId' => (string) $c['id'],
            ];
        }, $result['customers']);
    }

    public static function createTicket(array $config, array $body): array {
        $apiKey    = $config['apiKey'] ?? '';
        $subdomain = $config['subdomain'] ?? '';

        if (!$apiKey || !$subdomain) {
            Response::error('Syncro configuration missing', 400);
        }

        if (!self::validateSubdomain($subdomain)) {
            Response::error('Invalid Syncro subdomain format.', 400);
        }

        $url = "https://{$subdomain}.syncromsp.com/api/v1/tickets";
        $result = self::httpPost($url, $apiKey, [
            'customer_id' => $body['customerId'] ?? '',
            'subject'     => $body['subject'] ?? 'SmartRecur Appointment',
            'description' => $body['description'] ?? '',
            'status'      => 'New',
        ]);

        if (!isset($result['ticket'])) {
            Response::error('Failed to create Syncro ticket', 500);
        }

        return ['ticketId' => (string) ($result['ticket']['number'] ?? $result['ticket']['id'] ?? '')];
    }

    // ── HTTP helpers ────────────────────────────────────────

    private static function httpGet(string $url, string $apiKey): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $apiKey",
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

    private static function httpPost(string $url, string $apiKey, array $data): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $apiKey",
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return json_decode($body ?: '{}', true) ?: [];
    }
}
