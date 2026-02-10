<?php

class SettingsController {

    private static array $validKeys = [
        'branding', 'security', 'reminders', 'holidays',
        'manualClosures', 'businessHours', 'templates',
    ];

    public static function index(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $rows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = json_decode($row['setting_value'], true);
        }

        Response::json(['settings' => $settings]);
    }

    public static function update(array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ');

        foreach ($body as $key => $value) {
            if (!in_array($key, self::$validKeys, true)) continue;

            // Handle 2FA secret updates: sync to users table
            if ($key === 'security') {
                self::syncSecurityToUser($value);
            }

            $stmt->execute([$key, json_encode($value)]);
        }

        Response::success();
    }

    public static function backup(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $backup = [
            'version'   => '2.0',
            'timestamp' => time(),
            'data'      => [],
        ];

        $backup['data']['settings']    = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $backup['data']['events']      = $db->query('SELECT * FROM events')->fetchAll();
        $backup['data']['customers']   = $db->query('SELECT * FROM customers')->fetchAll();
        $backup['data']['services']    = $db->query('SELECT * FROM services')->fetchAll();
        $backup['data']['technicians'] = $db->query('SELECT * FROM technicians')->fetchAll();

        Response::json($backup);
    }

    public static function restore(array $body): void {
        AuthMiddleware::verify();

        if (!isset($body['data'])) {
            Response::error('Invalid backup format', 400);
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // Restore settings
            if (isset($body['data']['settings'])) {
                foreach ($body['data']['settings'] as $row) {
                    $key = $row['setting_key'] ?? null;
                    $val = $row['setting_value'] ?? null;
                    if (!$key || !$val) continue;
                    $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')
                       ->execute([$key, is_string($val) ? $val : json_encode($val)]);
                }
            }

            // Restore events
            if (isset($body['data']['events'])) {
                foreach ($body['data']['events'] as $row) {
                    $db->prepare('INSERT INTO events (id, title, customer_id, service_id, technician_id, asset_id, syncro_ticket_id, location_type, description, recurrence_rule, generated_dates, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), customer_id = VALUES(customer_id), service_id = VALUES(service_id), generated_dates = VALUES(generated_dates), status = VALUES(status)')
                       ->execute([
                           $row['id'], $row['title'] ?? '', $row['customer_id'] ?? '',
                           $row['service_id'] ?? '', $row['technician_id'] ?? null,
                           $row['asset_id'] ?? null, $row['syncro_ticket_id'] ?? null,
                           $row['location_type'] ?? 'ON_SITE', $row['description'] ?? '',
                           $row['recurrence_rule'] ?? '', $row['generated_dates'] ?? '[]',
                           $row['status'] ?? 'SCHEDULED',
                       ]);
                }
            }

            // Restore customers
            if (isset($body['data']['customers'])) {
                foreach ($body['data']['customers'] as $row) {
                    $db->prepare('INSERT INTO customers (id, company, name, email, phone, address, postcode, syncro_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE company = VALUES(company), name = VALUES(name)')
                       ->execute([
                           $row['id'], $row['company'] ?? '', $row['name'] ?? '',
                           $row['email'] ?? '', $row['phone'] ?? '',
                           $row['address'] ?? '', $row['postcode'] ?? '', $row['syncro_id'] ?? null,
                       ]);
                }
            }

            // Restore services
            if (isset($body['data']['services'])) {
                foreach ($body['data']['services'] as $row) {
                    $db->prepare('INSERT INTO services (id, name, type, default_duration_min, default_location, color, create_ticket, email_template_subject, email_template_body) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)')
                       ->execute([
                           $row['id'], $row['name'] ?? '', $row['type'] ?? 'RECURRING',
                           $row['default_duration_min'] ?? 60, $row['default_location'] ?? 'ON_SITE',
                           $row['color'] ?? '#4F46E5', (int)($row['create_ticket'] ?? 0),
                           $row['email_template_subject'] ?? null, $row['email_template_body'] ?? null,
                       ]);
                }
            }

            // Restore technicians
            if (isset($body['data']['technicians'])) {
                foreach ($body['data']['technicians'] as $row) {
                    $db->prepare('INSERT INTO technicians (id, name, email, color, skills) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)')
                       ->execute([
                           $row['id'], $row['name'] ?? '', $row['email'] ?? '',
                           $row['color'] ?? '#10B981', $row['skills'] ?? '[]',
                       ]);
                }
            }

            $db->commit();
            Response::success(['message' => 'Backup restored successfully']);
        } catch (\Exception $e) {
            $db->rollBack();
            // Don't expose internal error details to the client
            error_log('SmartRecur restore failed: ' . $e->getMessage());
            Response::error('Restore failed. Check server logs for details.', 500);
        }
    }

    /**
     * Keep the users table in sync when 2FA settings change.
     */
    private static function syncSecurityToUser(array $securitySettings): void {
        $db = Database::getInstance();
        // Update the first admin user
        $stmt = $db->prepare('
            UPDATE users SET two_factor_enabled = ?, two_factor_secret = ?
            WHERE id = (SELECT id FROM (SELECT id FROM users LIMIT 1) AS t)
        ');
        $stmt->execute([
            (int) ($securitySettings['twoFactorEnabled'] ?? false),
            $securitySettings['twoFactorSecret'] ?? null,
        ]);
    }
}
