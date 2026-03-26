<?php

class Env {
    private static bool $loaded = false;
    private static array $vars = [];

    public static function load(string $path = null): void {
        if (self::$loaded) return;
        $path = $path ?: dirname(__DIR__, 2) . '/.env';
        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip quotes
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
                $value = $m[2];
            }
            self::$vars[$key] = $value;
            putenv("$key=$value");
        }
        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string {
        return self::$vars[$key] ?? (getenv($key) ?: $default);
    }
}
