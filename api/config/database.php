<?php

require_once __DIR__ . '/../helpers/Env.php';

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            Env::load();
            $host    = Env::get('DB_HOST', 'localhost');
            $port    = Env::get('DB_PORT', '3306');
            $name    = Env::get('DB_NAME', 'smartrecur');
            $user    = Env::get('DB_USER', 'root');
            $pass    = Env::get('DB_PASS', '');
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=$charset";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (\PDOException $e) {
                error_log('SmartRecur DB connection failed: ' . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json');
                $response = ['error' => 'Database connection failed. Check server configuration.'];
                if (getenv('APP_DEBUG') === 'true') {
                    $response['debug'] = $e->getMessage();
                }
                echo json_encode($response);
                exit;
            }
        }
        return self::$instance;
    }
}
