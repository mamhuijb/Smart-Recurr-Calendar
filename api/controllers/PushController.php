<?php
/**
 * PushController — manages Web Push notification subscriptions and sending.
 * Stores subscriptions in the database. VAPID keys auto-generated on first use.
 */

require_once __DIR__ . '/../helpers/WebPush.php';

class PushController {

    /**
     * GET /push/config — return VAPID public key and push notification settings.
     */
    public static function config(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        self::ensureTables($db);

        $keys = self::getOrCreateVapidKeys($db);

        // Get push notification settings
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute(['pushNotifications']);
        $row = $stmt->fetch();
        $config = $row ? json_decode($row['setting_value'], true) : [];

        Response::json([
            'vapidPublicKey' => $keys['publicKey'],
            'enabled'        => $config['enabled'] ?? false,
            'timings'        => $config['timings'] ?? [15, 60, 1440], // minutes before: 15min, 1h, 1day
        ]);
    }

    /**
     * POST /push/subscribe — register a push subscription.
     */
    public static function subscribe(array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        self::ensureTables($db);

        $endpoint = $body['endpoint'] ?? '';
        $p256dh   = $body['keys']['p256dh'] ?? '';
        $auth     = $body['keys']['auth'] ?? '';

        if (!$endpoint || !$p256dh || !$auth) {
            Response::error('Invalid subscription data', 400);
            return;
        }

        // Upsert subscription
        $stmt = $db->prepare('
            INSERT INTO push_subscriptions (id, endpoint, p256dh, auth, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), created_at = NOW()
        ');
        $id = UUID::v4();
        $stmt->execute([$id, $endpoint, $p256dh, $auth]);

        Response::success();
    }

    /**
     * POST /push/unsubscribe — remove a push subscription.
     */
    public static function unsubscribe(array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        self::ensureTables($db);

        $endpoint = $body['endpoint'] ?? '';
        if (!$endpoint) {
            Response::error('Endpoint required', 400);
            return;
        }

        $db->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')->execute([$endpoint]);
        Response::success();
    }

    /**
     * POST /push/test — send a test push notification to all subscribed devices.
     */
    public static function test(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        self::ensureTables($db);

        $keys = self::getOrCreateVapidKeys($db);
        $subs = $db->query('SELECT * FROM push_subscriptions')->fetchAll();

        if (empty($subs)) {
            Response::error('No devices subscribed. Enable push notifications in your browser first.', 400);
            return;
        }

        $payload = json_encode([
            'title' => 'SmartRecur Test',
            'body'  => 'Push notifications are working! You will receive reminders for upcoming appointments.',
            'icon'  => '/icon-192.png',
            'tag'   => 'test-' . time(),
            'data'  => ['url' => '/'],
        ]);

        $sent = 0;
        $failed = 0;

        foreach ($subs as $sub) {
            $subscription = [
                'endpoint' => $sub['endpoint'],
                'keys' => [
                    'p256dh' => $sub['p256dh'],
                    'auth'   => $sub['auth'],
                ],
            ];

            $result = WebPush::send(
                $subscription,
                $payload,
                $keys['publicKey'],
                $keys['privateKey'],
                'mailto:' . Env::get('VAPID_SUBJECT', 'admin@smartrecur.local')
            );

            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
                // Remove invalid subscriptions (410 Gone = expired)
                if ($result['statusCode'] === 410 || $result['statusCode'] === 404) {
                    $db->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')
                       ->execute([$sub['endpoint']]);
                }
            }
        }

        Response::json([
            'success' => $sent > 0,
            'sent'    => $sent,
            'failed'  => $failed,
            'total'   => count($subs),
        ]);
    }

    /**
     * Send appointment reminder push to all subscribers.
     * Called from the cron script.
     */
    public static function sendReminder(
        \PDO $db,
        string $customerName,
        string $serviceName,
        string $dateStr,
        string $startTime,
        string $eventId
    ): array {
        self::ensureTables($db);

        $keys = self::getOrCreateVapidKeys($db);
        $subs = $db->query('SELECT * FROM push_subscriptions')->fetchAll();

        if (empty($subs)) return ['sent' => 0, 'total' => 0];

        $payload = json_encode([
            'title' => "Upcoming: $serviceName",
            'body'  => "Appointment with $customerName on $dateStr at $startTime",
            'icon'  => '/icon-192.png',
            'tag'   => "reminder-$eventId-$dateStr",
            'data'  => ['url' => '/', 'eventId' => $eventId],
            'requireInteraction' => true,
        ]);

        $sent = 0;
        foreach ($subs as $sub) {
            $result = WebPush::send(
                ['endpoint' => $sub['endpoint'], 'keys' => ['p256dh' => $sub['p256dh'], 'auth' => $sub['auth']]],
                $payload,
                $keys['publicKey'],
                $keys['privateKey'],
                'mailto:' . Env::get('VAPID_SUBJECT', 'admin@smartrecur.local')
            );
            if ($result['success']) $sent++;
            if ($result['statusCode'] === 410 || $result['statusCode'] === 404) {
                $db->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')->execute([$sub['endpoint']]);
            }
        }

        return ['sent' => $sent, 'total' => count($subs)];
    }

    // ── Helpers ──────────────────────────────────────────────────

    private static function getOrCreateVapidKeys(\PDO $db): array {
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute(['vapidKeys']);
        $row = $stmt->fetch();

        if ($row) {
            $keys = json_decode($row['setting_value'], true);
            if (!empty($keys['publicKey']) && !empty($keys['privateKey'])) {
                return $keys;
            }
        }

        // Generate new VAPID keys
        $keys = WebPush::generateVapidKeys();

        $stmt = $db->prepare('
            INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ');
        $stmt->execute(['vapidKeys', json_encode($keys)]);

        return $keys;
    }

    private static function ensureTables(\PDO $db): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $db->exec('CREATE TABLE IF NOT EXISTS push_subscriptions (
            id VARCHAR(36) PRIMARY KEY,
            endpoint VARCHAR(2048) NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_endpoint (endpoint(767))
        ) ENGINE=InnoDB');
    }
}
