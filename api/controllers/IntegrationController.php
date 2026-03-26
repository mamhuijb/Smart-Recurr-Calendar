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
            if (isset($item['config']['password']))      $item['config']['password'] = $item['config']['password'] ? '••••••••' : '';
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
                Office365Integration::ensureValidToken($config, $db);
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

        // Auto-set redirectUri if not configured
        if (empty($config['redirectUri'])) {
            $origin = Env::get('CORS_ORIGIN', '');
            if (!$origin || $origin === '*') {
                // Derive from current request
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $origin = "$proto://$host";
            }
            $config['redirectUri'] = $origin . '/api/integrations/office365/callback';
        }

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
            $config['expiresAt']    = $result['expiresAt'] ?? (time() + 3600);
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
     * Respects preferredMailMethod setting: 'auto' (SMTP > O365), 'smtp', or 'office365'.
     */
    public static function sendEmail(array $body): void {
        AuthMiddleware::verify();

        $to      = $body['to'] ?? '';
        $subject = $body['subject'] ?? '';
        $emailBody = $body['body'] ?? '';

        if (!$to || !$subject || !$emailBody) {
            Response::error('to, subject, and body are required', 400);
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address', 400);
        }

        $db = Database::getInstance();

        // Get preferred mail method from settings
        $prefStmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $prefStmt->execute(['preferredMailMethod']);
        $prefRow = $prefStmt->fetch();
        $preferred = $prefRow ? json_decode($prefRow['setting_value'], true) : 'auto';
        if (!in_array($preferred, ['auto', 'smtp', 'office365'])) $preferred = 'auto';

        // Load configs
        $smtpStmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $smtpStmt->execute(['smtp']);
        $smtpConfig = ($smtpStmt->fetch() ?: []);
        $smtpConfig = !empty($smtpConfig['config']) ? json_decode($smtpConfig['config'], true) : [];
        $smtpReady = !empty($smtpConfig['host']) && !empty($smtpConfig['username']);

        $o365Stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $o365Stmt->execute(['office365']);
        $o365Config = ($o365Stmt->fetch() ?: []);
        $o365Config = !empty($o365Config['config']) ? json_decode($o365Config['config'], true) : [];
        // Refresh token if expired
        if (!empty($o365Config['accessToken'])) {
            Office365Integration::ensureValidToken($o365Config, $db);
        }
        $o365Ready = !empty($o365Config['accessToken']);

        // Determine send order based on preference
        $tryOrder = [];
        if ($preferred === 'smtp')        $tryOrder = ['smtp'];
        elseif ($preferred === 'office365') $tryOrder = ['office365'];
        else                               $tryOrder = ['smtp', 'office365']; // auto

        $method = '';
        $success = false;
        $error = '';

        foreach ($tryOrder as $try) {
            try {
                if ($try === 'smtp' && $smtpReady) {
                    $result = SmtpMailer::send($smtpConfig, $to, $subject, $emailBody);
                    $method = 'smtp';
                    $success = $result['success'];
                    $error = $result['error'] ?? '';
                    break;
                } elseif ($try === 'office365' && $o365Ready) {
                    $result = Office365Integration::sendMail($o365Config, $to, $subject, $emailBody);
                    $method = 'office365';
                    $success = $result['success'];
                    $error = $result['error'] ?? '';
                    break;
                }
            } catch (\Exception $e) {
                $method = $try;
                $success = false;
                $error = $e->getMessage();
                error_log("SmartRecur email send error ({$try}): " . $e->getMessage());
                break;
            }
        }

        // Log the email attempt
        if ($method) {
            self::logEmail($db, $to, $subject, $success ? 'sent' : 'failed', $method, $error);
        }

        if (!$method) {
            Response::error('No email method configured. Set up SMTP or connect Office 365.', 400);
        } elseif ($success) {
            Response::json(['success' => true, 'method' => $method]);
        } else {
            Response::error(ucfirst($method) . ': ' . $error, 500);
        }
    }

    /**
     * GET /email-logs — list recent email logs.
     */
    public static function emailLogs(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        // Ensure table exists (auto-migrate for existing installs)
        $db->exec('CREATE TABLE IF NOT EXISTS email_logs (
            id VARCHAR(36) PRIMARY KEY,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            status ENUM(\'sent\', \'failed\') NOT NULL DEFAULT \'sent\',
            method VARCHAR(20) NOT NULL DEFAULT \'smtp\',
            error TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB');

        $rows = $db->query('SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 100')->fetchAll();
        Response::json(['logs' => $rows]);
    }

    private static function logEmail(\PDO $db, string $to, string $subject, string $status, string $method, string $error = ''): void {
        $id = UUID::v4();
        $stmt = $db->prepare('INSERT INTO email_logs (id, recipient, subject, status, method, error) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$id, $to, $subject, $status, $method, $error ?: null]);
    }

    /**
     * POST /integrations/invoiceninja/sync-customers — fetch clients from InvoiceNinja
     * and merge into local customers table.
     */
    public static function invoiceNinjaSyncCustomers(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        // Ensure invoiceninja_id column exists
        try {
            $db->query('SELECT invoiceninja_id FROM customers LIMIT 1');
        } catch (\PDOException $e) {
            $db->exec('ALTER TABLE customers ADD COLUMN invoiceninja_id VARCHAR(255) DEFAULT NULL');
            try { $db->exec('CREATE INDEX idx_invoiceninja_id ON customers (invoiceninja_id)'); } catch (\PDOException $e2) {}
        }

        $stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
        $stmt->execute(['invoiceninja']);
        $row = $stmt->fetch();
        $config = $row ? json_decode($row['config'], true) : [];

        if (empty($config['apiKey']) || empty($config['endpoint'])) {
            Response::error('InvoiceNinja not configured. Set API key and endpoint first.', 400);
            return;
        }

        $clients = InvoiceNinjaIntegration::getClients($config);
        if (empty($clients)) {
            Response::json(['imported' => 0, 'message' => 'No clients found or connection failed.']);
            return;
        }

        // Map InvoiceNinja clients to local customer format
        $imported = 0;
        foreach ($clients as $client) {
            $ninjaId = (string) ($client['id'] ?? '');
            if (!$ninjaId) continue;

            // Check if customer already exists by invoiceninja_id
            $existing = $db->prepare('SELECT id FROM customers WHERE invoiceninja_id = ?');
            $existing->execute([$ninjaId]);
            if ($existing->fetch()) continue;

            // Extract contact info
            $contacts = $client['contacts'] ?? [];
            $primaryContact = $contacts[0] ?? [];
            $name = trim(($primaryContact['first_name'] ?? '') . ' ' . ($primaryContact['last_name'] ?? ''));
            $email = $primaryContact['email'] ?? '';

            if (!$name && !($client['name'] ?? '')) continue;

            $id = UUID::v4();
            $insertStmt = $db->prepare('
                INSERT INTO customers (id, name, email, phone, company, address, postcode, invoiceninja_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insertStmt->execute([
                $id,
                $name ?: ($client['name'] ?? 'Unknown'),
                $email,
                $primaryContact['phone'] ?? ($client['phone'] ?? ''),
                $client['name'] ?? '',
                trim(($client['address1'] ?? '') . ' ' . ($client['address2'] ?? '')),
                $client['postal_code'] ?? '',
                $ninjaId,
            ]);
            $imported++;
        }

        Response::json([
            'imported' => $imported,
            'total'    => count($clients),
            'message'  => "Imported {$imported} new customers from InvoiceNinja ({$imported} of " . count($clients) . " were new).",
        ]);
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
        Office365Integration::ensureValidToken($config, $db);

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
        Office365Integration::ensureValidToken($config, $db);

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
