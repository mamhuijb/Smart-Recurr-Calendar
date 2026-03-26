<?php

class CustomerController {

    // Auto-migrate: add invoiceninja_id column if missing
    private static function ensureInvoiceNinjaColumn(\PDO $db): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $db->query('SELECT invoiceninja_id FROM customers LIMIT 1');
        } catch (\PDOException $e) {
            $db->exec('ALTER TABLE customers ADD COLUMN invoiceninja_id VARCHAR(255) DEFAULT NULL');
            try {
                $db->exec('CREATE INDEX idx_invoiceninja_id ON customers (invoiceninja_id)');
            } catch (\PDOException $e2) {
                // Index may already exist
            }
        }
    }

    public static function index(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        self::ensureInvoiceNinjaColumn($db);

        $customers = $db->query('SELECT * FROM customers ORDER BY company ASC')->fetchAll();

        // Attach assets
        $result = array_map(function ($c) use ($db) {
            $stmt = $db->prepare('SELECT id, name, type FROM assets WHERE customer_id = ?');
            $stmt->execute([$c['id']]);
            $c['assets'] = $stmt->fetchAll();
            return self::toFrontend($c);
        }, $customers);

        Response::json(['customers' => $result]);
    }

    public static function store(array $body): void {
        AuthMiddleware::verify();

        $id = $body['id'] ?? UUID::v4();
        $db = Database::getInstance();

        $stmt = $db->prepare('
            INSERT INTO customers (id, company, name, email, phone, address, postcode, syncro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $id,
            $body['company'] ?? '',
            $body['name'] ?? '',
            $body['email'] ?? '',
            $body['phone'] ?? '',
            $body['address'] ?? '',
            $body['postcode'] ?? '',
            $body['syncroId'] ?? null,
        ]);

        Response::json(['customer' => ['id' => $id]], 201);
    }

    public static function update(string $id, array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE customers SET company = ?, name = ?, email = ?, phone = ?,
                                 address = ?, postcode = ?, syncro_id = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $body['company'] ?? '',
            $body['name'] ?? '',
            $body['email'] ?? '',
            $body['phone'] ?? '',
            $body['address'] ?? '',
            $body['postcode'] ?? '',
            $body['syncroId'] ?? null,
            $id,
        ]);

        Response::success();
    }

    public static function destroy(string $id): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        $db->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
        Response::success();
    }

    // ── Helpers ─────────────────────────────────────────────

    private static function toFrontend(array $c): array {
        return [
            'id'              => $c['id'],
            'company'         => $c['company'],
            'name'            => $c['name'],
            'email'           => $c['email'] ?? '',
            'phone'           => $c['phone'] ?? '',
            'address'         => $c['address'] ?? '',
            'postcode'        => $c['postcode'] ?? '',
            'syncroId'        => $c['syncro_id'] ?? '',
            'invoiceninjaId'  => $c['invoiceninja_id'] ?? '',
            'assets'          => $c['assets'] ?? [],
        ];
    }

}
