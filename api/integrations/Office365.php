<?php

class Office365Integration {

    private static string $tokenUrl = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token';
    private static string $authUrl  = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize';
    private static string $graphUrl = 'https://graph.microsoft.com/v1.0';

    public static function getAuthUrl(array $config, string $state = ''): string {
        $tenant   = $config['tenantId'] ?: 'common';
        $clientId = $config['clientId'] ?? '';
        $redirect = $config['redirectUri'] ?? '';
        $scopes   = 'User.Read Calendars.ReadWrite Mail.Send offline_access';

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
            'scope'         => 'User.Read Calendars.ReadWrite Mail.Send offline_access',
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

        $expiresAt = time() + ($response['expires_in'] ?? 3600);

        return [
            'success'      => true,
            'accessToken'  => $response['access_token'],
            'refreshToken' => $response['refresh_token'] ?? '',
            'expiresAt'    => $expiresAt,
            'email'        => $email,
        ];
    }

    /**
     * Refresh the access token using a stored refresh token.
     * Returns updated config array with new tokens, or null on failure.
     */
    public static function refreshToken(array $config): ?array {
        $refreshToken = $config['refreshToken'] ?? '';
        if (!$refreshToken) return null;

        $tenant = $config['tenantId'] ?: 'common';
        $url = str_replace('{tenant}', $tenant, self::$tokenUrl);

        $response = self::httpPost($url, [
            'client_id'     => $config['clientId'] ?? '',
            'client_secret' => $config['clientSecret'] ?? '',
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
            'scope'         => 'User.Read Calendars.ReadWrite Mail.Send offline_access',
        ], 'application/x-www-form-urlencoded');

        if (!isset($response['access_token'])) return null;

        $config['accessToken']  = $response['access_token'];
        $config['refreshToken'] = $response['refresh_token'] ?? $refreshToken;
        $config['expiresAt']    = time() + ($response['expires_in'] ?? 3600);

        return $config;
    }

    /**
     * Get a valid access token, refreshing if expired. Persists to DB if refreshed.
     */
    public static function ensureValidToken(array &$config, \PDO $db = null): bool {
        $expiresAt = $config['expiresAt'] ?? 0;
        $token = $config['accessToken'] ?? '';

        if (!$token) return false;

        // Refresh if expiring within 5 minutes
        if ($expiresAt && $expiresAt < (time() + 300)) {
            $refreshed = self::refreshToken($config);
            if ($refreshed) {
                $config = $refreshed;
                // Persist refreshed tokens to DB
                if ($db) {
                    $stmt = $db->prepare('UPDATE integration_configs SET config = ? WHERE id = ?');
                    $stmt->execute([json_encode($config), 'office365']);
                }
                return true;
            }
            return false;
        }

        return true;
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

    public static function getCalendarEvents(array $config, string $calendarId = ''): array {
        $token = $config['accessToken'] ?? '';
        if (!$token) return [];

        $endpoint = $calendarId
            ? self::$graphUrl . "/me/calendars/{$calendarId}/events?\$top=50&\$orderby=start/dateTime"
            : self::$graphUrl . '/me/calendar/events?$top=50&$orderby=start/dateTime';
        $result = self::httpGet($endpoint, $token);
        return $result['value'] ?? [];
    }

    /**
     * List available calendars for the connected user.
     */
    public static function getCalendars(array $config): array {
        $token = $config['accessToken'] ?? '';
        if (!$token) return [];

        $result = self::httpGet(self::$graphUrl . '/me/calendars', $token);
        return $result['value'] ?? [];
    }

    /**
     * Create a calendar event in Office 365.
     */
    public static function createCalendarEvent(array $config, array $event, string $calendarId = ''): array {
        $token = $config['accessToken'] ?? '';
        if (!$token) return ['success' => false, 'error' => 'Not connected to Office 365'];

        $endpoint = $calendarId
            ? self::$graphUrl . "/me/calendars/{$calendarId}/events"
            : self::$graphUrl . '/me/calendar/events';

        $result = self::httpPostJson($endpoint, $event, $token);
        if (isset($result['id'])) {
            return ['success' => true, 'eventId' => $result['id']];
        }
        return ['success' => false, 'error' => $result['error']['message'] ?? 'Failed to create event'];
    }

    /**
     * Send an email via Microsoft Graph API.
     */
    public static function sendMail(array $config, string $to, string $subject, string $body): array {
        $token = $config['accessToken'] ?? '';
        if (!$token) return ['success' => false, 'error' => 'Not connected to Office 365'];

        $mailData = [
            'message' => [
                'subject' => $subject,
                'body'    => [
                    'contentType' => 'Text',
                    'content'     => $body,
                ],
                'toRecipients' => [
                    ['emailAddress' => ['address' => $to]],
                ],
            ],
            'saveToSentItems' => true,
        ];

        $result = self::httpPostJson(self::$graphUrl . '/me/sendMail', $mailData, $token);

        // sendMail returns 202 with no body on success
        if (empty($result) || !isset($result['error'])) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => $result['error']['message'] ?? 'Send failed'];
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
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => ['message' => "Connection failed: $err"]];
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 400) {
            $decoded = json_decode($body, true);
            return $decoded ?: ['error' => ['message' => "HTTP $code"]];
        }
        return json_decode($body ?: '{}', true) ?: [];
    }

    private static function httpPostJson(string $url, array $data, string $token): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);
        $result = curl_exec($ch);
        if ($result === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => ['message' => "Connection failed: $err"]];
        }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // 202 = accepted (sendMail), 201 = created (event)
        if ($code >= 200 && $code < 300 && empty($result)) {
            return [];
        }
        return json_decode($result ?: '{}', true) ?: [];
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
        if ($result === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['error' => "Connection failed: $err"];
        }
        curl_close($ch);
        return json_decode($result ?: '{}', true) ?: [];
    }
}
