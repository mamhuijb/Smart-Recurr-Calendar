<?php

require_once __DIR__ . '/../integrations/Office365.php';
require_once __DIR__ . '/../integrations/SyncroMSP.php';
require_once __DIR__ . '/../integrations/InvoiceNinja.php';
require_once __DIR__ . '/../integrations/Zoho.php';
require_once __DIR__ . '/../helpers/SmtpMailer.php';

class IntegrationController {

    /**
     * GET /integrations/status — return connection status for all integrations.
     */
    public static function status(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $rows = $db->query('SELECT * FROM integration_configs')->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['id']] = [
                'isConnected' => (bool) $row['is_connected'],
                'lastChecked' => $row['last_checked'],
                'config'      => json_decode($row['config'], true),
            ];
        }

        // Strip secrets from response
        foreach ($result as $key => &$item) {
            if (isset($item['config']['clientSecret'])) $item['config']['clientSecret'] = $item['config']['clientSecret'] ? '••••••••' : '';
            if (isset($item['config']['apiKey']))        $item['config']['apiKey'] = $item['config']['apiKey'] ? '••••••••' : '';
            if (isset($item['config']['apiSecret']))     $item['config']['apiSecret'] = $item['config']['apiSecret'] ? '••••••••' : '';
            if (isset($item['config']['accessToken']))   unset($item['config']['accessToken']);
        }

        Response::json(['integrations' => $result]);
    }

    /**
     * PUT /integrations/:type/config — save integration configuration.
     */
    public static function saveConfig(string $type, array $body): void {
        AuthMiddleware::verify();

        $valid = ['office365', 'syncro', 'invoiceninja', 'zoho', 'smtp'];
        if (!in_array($type, $valid, true)) {
            Response::error('Unknown integration type', 400);
        }

        $db = Database::getInstance();

        // Merge with existing config so we don't lose secrets not re-sent
        $stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $stmt->execute([$type]);
        $row = $stmt->fetch();
        $existing = $row ? json_decode($row['config'], true) : [];

        // Only overwrite fields that are actually sent (and not masked)
        foreach ($body as $key => $value) {
            if ($value === '••••••••') continue; // Don't overwrite with mask
            $existing[$key] = $value;
        }

        $stmt = $db->prepare('
            INSERT INTO integration_configs (id, config) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE config = VALUES(config)
        ');
        $stmt->execute([$type, json_encode($existing)]);

        Response::success();
    }

    /**
     * POST /integrations/:type/test — test an integration connection.
     */
    public static function test(string $type): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $stmt->execute([$type]);
        $row = $stmt->fetch();
        $config = $row ? json_decode($row['config'], true) : [];

        $connected = false;
        $message   = '';

        switch ($type) {
            case 'office365':
                [$connected, $message] = Office365Integration::test($config);
                break;
            case 'syncro':
                [$connected, $message] = SyncroMSPIntegration::test($config);
                break;
            case 'invoiceninja':
                [$connected, $message] = InvoiceNinjaIntegration::test($config);
                break;
            case 'zoho':
                [$connected, $message] = ZohoIntegration::test($config);
                break;
            case 'smtp':
                [$connected, $message] = SmtpMailer::test($config);
                break;
            default:
                Response::error('Unknown integration', 400);
        }

        // Persist status
        $db->prepare('UPDATE integration_configs SET is_connected = ?, last_checked = NOW() WHERE id = ?')
           ->execute([(int) $connected, $type]);

        Response::json([
            'isConnected' => $connected,
            'message'     => $message,
        ]);
    }

    // ── Office 365 ──────────────────────────────────────────

    public static function office365AuthUrl(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $stmt->execute(['office365']);
        $config = json_decode($stmt->fetch()['config'] ?? '{}', true);

        // Generate and persist a CSRF state token
        $state = bin2hex(random_bytes(16));
        $config['_oauth_state'] = $state;
        $db->prepare('UPDATE integration_configs SET config = ? WHERE id = ?')
           ->execute([json_encode($config), 'office365']);

        $url = Office365Integration::getAuthUrl($config, $state);
        Response::json(['url' => $url]);
    }

    public static function office365Callback(): void {
        $code  = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';

        if (!$code) {
            self::oauthResponse(false, 'No authorization code provided');
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $stmt->execute(['office365']);
        $config = json_decode($stmt->fetch()['config'] ?? '{}', true);

        // Validate OAuth state parameter to prevent CSRF
        $expectedState = $config['_oauth_state'] ?? '';
        if (!$expectedState || !hash_equals($expectedState, $state)) {
            self::oauthResponse(false, 'Invalid state parameter');
            return;
        }

        // Clear used state
        unset($config['_oauth_state']);

        $result = Office365Integration::exchangeCode($code, $config);

        if ($result['success']) {
            $config['accessToken']  = $result['accessToken'];
            $config['refreshToken'] = $result['refreshToken'] ?? '';
            $config['userEmail']    = $result['email'];
            $db->prepare('UPDATE integration_configs SET config = ?, is_connected = 1, last_checked = NOW() WHERE id = ?')
               ->execute([json_encode($config), 'office365']);

            self::oauthResponse(true, '', $result['email']);
        } else {
            self::oauthResponse(false, $result['error'] ?? 'Token exchange failed');
        }
    }

    /**
     * Render a safe HTML page for OAuth popup postMessage.
     * Uses json_encode to prevent XSS in injected values.
     */
    private static function oauthResponse(bool $success, string $error = '', string $email = ''): void {
        $origin = Env::get('CORS_ORIGIN', '*');
        $payload = $success
            ? json_encode(['type' => 'OAUTH_SUCCESS', 'email' => $email], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            : json_encode(['type' => 'OAUTH_ERROR', 'error' => $error], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $originJs = json_encode($origin === '*' ? '*' : $origin);
        $heading  = $success
            ? htmlspecialchars("Connected as $email. You can close this window.", ENT_QUOTES, 'UTF-8')
            : htmlspecialchars("Error: $error", ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=UTF-8');
        echo "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>OAuth</title></head>";
        echo "<body><h3>{$heading}</h3>";
        echo "<script>if(window.opener){window.opener.postMessage({$payload},{$originJs});}window.close();</script>";
        echo "</body></html>";
        exit;
    }

    // ── Syncro MSP ──────────────────────────────────────────

    public static function syncroCustomers(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $stmt->execute(['syncro']);
        $config = json_decode($stmt->fetch()['config'] ?? '{}', true);

        $customers = SyncroMSPIntegration::getCustomers($config);
        Response::json(['customers' => $customers]);
    }

    /**
     * POST /integrations/email/send — send an email via configured method (SMTP or Office 365).
     */
    public static function sendEmail(array $body): void {
        AuthMiddleware::verify();

        $to      = $body['to'] ?? '';
        $subject = $body['subject'] ?? '';
        $emailBody = $body['body'] ?? '';

        if (!$to || !$subject || !$emailBody) {
            Response::error('to, subject, and body are required', 400);
        }

        // Validate email format
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address', 400);
        }

        $db = Database::getInstance();

        // Check which email method is configured
        // Priority: SMTP > Office 365 > fail
        $smtpStmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $smtpStmt->execute(['smtp']);
        $smtpRow = $smtpStmt->fetch();
        $smtpConfig = $smtpRow ? json_decode($smtpRow['config'], true) : [];

        if (!empty($smtpConfig['host']) && !empty($smtpConfig['username'])) {
            // Use SMTP
            $result = SmtpMailer::send($smtpConfig, $to, $subject, $emailBody);
            if ($result['success']) {
                Response::json(['success' => true, 'method' => 'smtp']);
            } else {
                Response::error('SMTP: ' . $result['error'], 500);
            }
        }

        // Try Office 365
        $o365Stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $o365Stmt->execute(['office365']);
        $o365Row = $o365Stmt->fetch();
        $o365Config = $o365Row ? json_decode($o365Row['config'], true) : [];

        if (!empty($o365Config['accessToken'])) {
            $result = Office365Integration::sendMail($o365Config, $to, $subject, $emailBody);
            if ($result['success']) {
                Response::json(['success' => true, 'method' => 'office365']);
            } else {
                Response::error('Office 365: ' . ($result['error'] ?? 'Send failed'), 500);
            }
        }

        Response::error('No email method configured. Set up SMTP or connect Office 365.', 400);
    }

    public static function syncroCreateTicket(array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $stmt->execute(['syncro']);
        $config = json_decode($stmt->fetch()['config'] ?? '{}', true);

        $result = SyncroMSPIntegration::createTicket($config, $body);
        Response::json($result);
    }

    // ── Office 365 Calendar Sync ─────────────────────────────

    /**
     * GET /integrations/office365/calendars — list user's calendars.
     */
    public static function office365Calendars(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $stmt->execute(['office365']);
        $config = json_decode($stmt->fetch()['config'] ?? '{}', true);

        $calendars = Office365Integration::getCalendars($config);
        Response::json(['calendars' => $calendars]);
    }

    /**
     * POST /integrations/office365/calendar-event — create a calendar event in Office 365.
     */
    public static function office365CreateEvent(array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $stmt->execute(['office365']);
        $config = json_decode($stmt->fetch()['config'] ?? '{}', true);

        $calendarId = $body['calendarId'] ?? '';
        $event = [
            'subject' => $body['subject'] ?? 'SmartRecur Appointment',
            'body' => [
                'contentType' => 'Text',
                'content' => $body['description'] ?? '',
            ],
            'start' => [
                'dateTime' => $body['startDateTime'] ?? '',
                'timeZone' => $body['timeZone'] ?? 'Europe/Amsterdam',
            ],
            'end' => [
                'dateTime' => $body['endDateTime'] ?? '',
                'timeZone' => $body['timeZone'] ?? 'Europe/Amsterdam',
            ],
        ];

        $result = Office365Integration::createCalendarEvent($config, $event, $calendarId);
        if ($result['success']) {
            Response::json($result);
        } else {
            Response::error($result['error'], 500);
        }
    }
}
