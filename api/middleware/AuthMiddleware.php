<?php

require_once __DIR__ . '/../helpers/JWT.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthMiddleware {
    public static function verify(): array {
        $config = require __DIR__ . '/../config/app.php';

        $headers = function_exists('getallheaders') ? getallheaders() : self::getHeaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.+)$/i', $auth, $matches)) {
            Response::error('Authentication required', 401);
        }

        $payload = JWT::decode(trim($matches[1]), $config['jwt_secret']);
        if (!$payload) {
            Response::error('Invalid or expired token', 401);
        }

        return $payload;
    }

    /**
     * Fallback header reader for environments where getallheaders() is unavailable.
     */
    private static function getHeaders(): array {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $name = ucwords(strtolower($name), '-');
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
}
