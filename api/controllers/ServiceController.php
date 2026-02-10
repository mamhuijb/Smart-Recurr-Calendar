<?php

class ServiceController {

    public static function index(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        $rows = $db->query('SELECT * FROM services ORDER BY name ASC')->fetchAll();
        Response::json(['services' => array_map([self::class, 'toFrontend'], $rows)]);
    }

    public static function store(array $body): void {
        AuthMiddleware::verify();

        $id = $body['id'] ?? UUID::v4();
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT INTO services (id, name, type, default_duration_min, default_location,
                                  color, create_ticket, email_template_subject, email_template_body)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        ]);

        Response::json(['service' => ['id' => $id]], 201);
    }

    public static function update(string $id, array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE services SET name = ?, type = ?, default_duration_min = ?, default_location = ?,
                                color = ?, create_ticket = ?, email_template_subject = ?,
                                email_template_body = ?
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
        if ($r['email_template_subject'] || $r['email_template_body']) {
            $template = [
                'subject' => $r['email_template_subject'] ?? '',
                'body'    => $r['email_template_body'] ?? '',
            ];
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
        ];
    }

}
