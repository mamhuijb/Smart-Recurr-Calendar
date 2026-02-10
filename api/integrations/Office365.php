<?php

class Office365Integration {

    private static string $tokenUrl = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token';
    private static string $authUrl  = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize';
    private static string $graphUrl = 'https://graph.microsoft.com/v1.0';

    public static function getAuthUrl(array $config, string $state = ''): string {
        $tenant   = $config['tenantId'] ?: 'common';
        $clientId = $config['clientId'] ?? '';
        $redirect = $config['redirectUri'] ?? '';
        $scopes   = 'User.Read Calendars.ReadWrite offline_access';

        if (!$state) {
            $state = bin2hex(random_bytes(16));
        }

        $params = http_build_query([
            'client_id'     => $clientId,
            'response_type' => 'code',
            'redirect_uri'  => $redirect,
            'response_mode' => 'query',
            'scope'         => $scopes,
            'state'         => $state,
        ]);

        $url = str_replace('{tenant}', urlencode($tenant), self::$authUrl);
        return "$url?$params";
    }

    public static function exchangeCode(string $code, array $config): array {
        $tenant = $config['tenantId'] ?: 'common';
        $url    = str_replace('{tenant}', $tenant, self::$tokenUrl);

        $response = self::httpPost($url, [
            'client_id'     => $config['clientId'] ?? '',
            'client_secret' => $config['clientSecret'] ?? '',
            'code'          => $code,
            'redirect_uri'  => $config['redirectUri'] ?? '',
            'grant_type'    => 'authorization_code',
            'scope'         => 'User.Read Calendars.ReadWrite offline_access',
        ], 'application/x-www-form-urlencoded');

        if (!isset($response['access_token'])) {
            return ['success' => false, 'error' => $response['error_description'] ?? 'Token exchange failed'];
        }

        // Fetch user profile
        $email = 'user@office365';
        $profile = self::httpGet(self::$graphUrl . '/me', $response['access_token']);
        if (isset($profile['mail']) || isset($profile['userPrincipalName'])) {
            $email = $profile['mail'] ?? $profile['userPrincipalName'];
        }

        return [
            'success'      => true,
            'accessToken'  => $response['access_token'],
            'refreshToken' => $response['refresh_token'] ?? '',
            'email'        => $email,
        ];
    }

    public static function test(array $config): array {
        $token = $config['accessToken'] ?? '';
        if (!$token) {
            return [false, 'No access token. Please connect via OAuth first.'];
        }

        $result = self::httpGet(self::$graphUrl . '/me', $token);
        if (isset($result['error'])) {
            return [false, 'Token expired or invalid: ' . ($result['error']['message'] ?? 'Unknown error')];
        }

        $email = $result['mail'] ?? $result['userPrincipalName'] ?? 'Unknown';
        return [true, "Connected as $email"];
    }

    public static function getCalendarEvents(array $config): array {
        $token = $config['accessToken'] ?? '';
        if (!$token) return [];

        $result = self::httpGet(self::$graphUrl . '/me/calendar/events?$top=50&$orderby=start/dateTime', $token);
        return $result['value'] ?? [];
    }

    // ── HTTP helpers ────────────────────────────────────────

    private static function httpGet(string $url, string $token): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return json_decode($body ?: '{}', true) ?: [];
    }

    private static function httpPost(string $url, array $data, string $contentType = 'application/json'): array {
        $ch = curl_init($url);
        $headers = ["Accept: application/json"];

        if ($contentType === 'application/x-www-form-urlencoded') {
            $body = http_build_query($data);
            $headers[] = "Content-Type: application/x-www-form-urlencoded";
        } else {
            $body = json_encode($data);
            $headers[] = "Content-Type: application/json";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result ?: '{}', true) ?: [];
    }
}
