<?php

/**
 * Simple file-based rate limiter for login brute-force protection.
 * In production with multiple workers, consider a Redis/DB-based approach.
 */
class RateLimiter {
    private static string $dir = '/tmp/smartrecur_ratelimit';
    private static int $maxAttempts = 5;
    private static int $windowSec = 300; // 5 minutes

    public static function check(string $key): bool {
        self::ensureDir();
        $file = self::$dir . '/' . md5($key);

        if (!file_exists($file)) return true;

        $data = json_decode(file_get_contents($file), true);
        if (!$data) return true;

        // Clean expired entries
        $now = time();
        $data = array_filter($data, fn($t) => $t > $now - self::$windowSec);

        if (count($data) >= self::$maxAttempts) {
            return false; // Rate limited
        }

        return true;
    }

    public static function hit(string $key): void {
        self::ensureDir();
        $file = self::$dir . '/' . md5($key);

        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: [];
        }

        $now = time();
        $data = array_filter($data, fn($t) => $t > $now - self::$windowSec);
        $data[] = $now;

        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    public static function clear(string $key): void {
        $file = self::$dir . '/' . md5($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }

    private static function ensureDir(): void {
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0700, true);
        }
    }
}
