<?php

class TechnicianController {

    public static function index(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        $rows = $db->query('SELECT * FROM technicians ORDER BY name ASC')->fetchAll();
        Response::json(['technicians' => array_map([self::class, 'toFrontend'], $rows)]);
    }

    public static function store(array $body): void {
        AuthMiddleware::verify();

        $id = $body['id'] ?? UUID::v4();
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT INTO technicians (id, name, email, color, skills)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $id,
            $body['name'] ?? '',
            $body['email'] ?? '',
            $body['color'] ?? '#10B981',
            json_encode($body['skills'] ?? []),
        ]);

        Response::json(['technician' => ['id' => $id]], 201);
    }

    public static function update(string $id, array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE technicians SET name = ?, email = ?, color = ?, skills = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $body['name'] ?? '',
            $body['email'] ?? '',
            $body['color'] ?? '#10B981',
            json_encode($body['skills'] ?? []),
            $id,
        ]);

        Response::success();
    }

    public static function destroy(string $id): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        $db->prepare('DELETE FROM technicians WHERE id = ?')->execute([$id]);
        Response::success();
    }

    private static function toFrontend(array $r): array {
        return [
            'id'     => $r['id'],
            'name'   => $r['name'],
            'email'  => $r['email'] ?? '',
            'color'  => $r['color'],
            'skills' => json_decode($r['skills'] ?? '[]', true) ?: [],
        ];
    }

}
