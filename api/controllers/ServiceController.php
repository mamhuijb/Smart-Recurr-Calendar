<?php

class ServiceController {

    public static function index(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        // Auto-migrate: add reminder_days column if missing
        self::ensureReminderDaysColumn($db);

        $rows = $db->query('SELECT * FROM services ORDER BY name ASC')->fetchAll();
        Response::json(['services' => array_map([self::class, 'toFrontend'], $rows)]);
    }

    public static function store(array $body): void {
        AuthMiddleware::verify();

        $id = $body['id'] ?? UUID::v4();
        $db = Database::getInstance();
        self::ensureReminderDaysColumn($db);

        $reminderDays = isset($body['reminderDays']) && is_array($body['reminderDays']) && count($body['reminderDays']) > 0
            ? json_encode(array_map('intval', $body['reminderDays']))
            : null;

        $stmt = $db->prepare('
            INSERT INTO services (id, name, type, default_duration_min, default_location,
                                  color, create_ticket, email_template_subject, email_template_body, reminder_days)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $id,
            $body['name'] ?? '',
            $body['type'] ?? 'RECURRING',
            $body['defaultDurationMin'] ?? 60,
            $body['defaultLocation'] ?? 'ON_SITE',
            $body['color'] ?? '#4F46E5',
            (int) ($body['createTicket'] ?? false),
            $body['emailTemplate']['subject'] ?? null,
            $body['emailTemplate']['body'] ?? null,
            $reminderDays,
        ]);

        Response::json(['service' => ['id' => $id]], 201);
    }

    public static function update(string $id, array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        self::ensureReminderDaysColumn($db);

        $reminderDays = isset($body['reminderDays']) && is_array($body['reminderDays']) && count($body['reminderDays']) > 0
            ? json_encode(array_map('intval', $body['reminderDays']))
            : null;

        $stmt = $db->prepare('
            UPDATE services SET name = ?, type = ?, default_duration_min = ?, default_location = ?,
                                color = ?, create_ticket = ?, email_template_subject = ?,
                                email_template_body = ?, reminder_days = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $body['name'] ?? '',
            $body['type'] ?? 'RECURRING',
            $body['defaultDurationMin'] ?? 60,
            $body['defaultLocation'] ?? 'ON_SITE',
            $body['color'] ?? '#4F46E5',
            (int) ($body['createTicket'] ?? false),
            $body['emailTemplate']['subject'] ?? null,
            $body['emailTemplate']['body'] ?? null,
            $reminderDays,
            $id,
        ]);

        Response::success();
    }

    public static function destroy(string $id): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        $db->prepare('DELETE FROM services WHERE id = ?')->execute([$id]);
        Response::success();
    }

    private static function toFrontend(array $r): array {
        $template = null;
        if (!empty($r['email_template_subject']) || !empty($r['email_template_body'])) {
            $template = [
                'subject' => $r['email_template_subject'] ?? '',
                'body'    => $r['email_template_body'] ?? '',
            ];
        }

        $reminderDays = null;
        if (!empty($r['reminder_days'])) {
            $decoded = json_decode($r['reminder_days'], true);
            if (is_array($decoded) && count($decoded) > 0) {
                $reminderDays = $decoded;
            }
        }

        return [
            'id'                 => $r['id'],
            'name'               => $r['name'],
            'type'               => $r['type'],
            'defaultDurationMin' => (int) $r['default_duration_min'],
            'defaultLocation'    => $r['default_location'],
            'color'              => $r['color'],
            'createTicket'       => (bool) $r['create_ticket'],
            'emailTemplate'      => $template,
            'reminderDays'       => $reminderDays,
        ];
    }

    /**
     * Auto-add reminder_days column for existing installs that don't have it yet.
     */
    private static function ensureReminderDaysColumn(\PDO $db): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $db->query('SELECT reminder_days FROM services LIMIT 1');
        } catch (\PDOException $e) {
            $db->exec('ALTER TABLE services ADD COLUMN reminder_days JSON DEFAULT NULL');
        }
    }
}
