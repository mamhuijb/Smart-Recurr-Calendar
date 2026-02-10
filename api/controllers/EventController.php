<?php

class EventController {

    public static function index(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $rows = $db->query('SELECT * FROM events ORDER BY created_at DESC')->fetchAll();

        $events = array_map(function ($r) {
            $r['generated_dates'] = json_decode($r['generated_dates'], true) ?? [];
            return self::toFrontend($r);
        }, $rows);

        Response::json(['events' => $events]);
    }

    public static function store(array $body): void {
        AuthMiddleware::verify();

        $id = $body['id'] ?? UUID::v4();
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO events (id, title, customer_id, service_id, technician_id, asset_id,
                                syncro_ticket_id, location_type, description, recurrence_rule,
                                generated_dates, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $id,
            $body['title'] ?? '',
            $body['customerId'] ?? '',
            $body['serviceId'] ?? '',
            $body['technicianId'] ?? null,
            $body['assetId'] ?? null,
            $body['syncroTicketId'] ?? null,
            $body['locationType'] ?? 'ON_SITE',
            $body['description'] ?? '',
            $body['recurrenceRule'] ?? '',
            json_encode($body['generatedDates'] ?? []),
            $body['status'] ?? 'SCHEDULED',
        ]);

        $stmt = $db->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $row['generated_dates'] = json_decode($row['generated_dates'], true) ?? [];

        Response::json(['event' => self::toFrontend($row)], 201);
    }

    public static function update(string $id, array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE events SET title = ?, customer_id = ?, service_id = ?, technician_id = ?,
                              asset_id = ?, syncro_ticket_id = ?, location_type = ?,
                              description = ?, recurrence_rule = ?, generated_dates = ?, status = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $body['title'] ?? '',
            $body['customerId'] ?? '',
            $body['serviceId'] ?? '',
            $body['technicianId'] ?? null,
            $body['assetId'] ?? null,
            $body['syncroTicketId'] ?? null,
            $body['locationType'] ?? 'ON_SITE',
            $body['description'] ?? '',
            $body['recurrenceRule'] ?? '',
            json_encode($body['generatedDates'] ?? []),
            $body['status'] ?? 'SCHEDULED',
            $id,
        ]);

        Response::success();
    }

    public static function destroy(string $id): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        $db->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
        Response::success();
    }

    // ── Helpers ─────────────────────────────────────────────

    private static function toFrontend(array $r): array {
        return [
            'id'              => $r['id'],
            'title'           => $r['title'],
            'customerId'      => $r['customer_id'],
            'serviceId'       => $r['service_id'],
            'technicianId'    => $r['technician_id'] ?? '',
            'assetId'         => $r['asset_id'] ?? '',
            'syncroTicketId'  => $r['syncro_ticket_id'] ?? '',
            'locationType'    => $r['location_type'],
            'description'     => $r['description'] ?? '',
            'recurrenceRule'  => $r['recurrence_rule'] ?? '',
            'generatedDates'  => $r['generated_dates'] ?? [],
            'status'          => $r['status'],
            'createdAt'       => strtotime($r['created_at']) * 1000,
        ];
    }

}
