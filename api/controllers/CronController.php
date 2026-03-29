<?php

/**
 * CronController — manages cron job status, logging, and configuration.
 * Provides endpoints for the admin panel to monitor and control scheduled tasks.
 */
class CronController {

    /**
     * Ensure cron_logs table exists.
     */
    private static function ensureTable(\PDO $db): void {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $db->exec('CREATE TABLE IF NOT EXISTS cron_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_name VARCHAR(100) NOT NULL,
            started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at TIMESTAMP NULL,
            duration_ms INT DEFAULT NULL,
            status ENUM(\'running\', \'success\', \'error\') NOT NULL DEFAULT \'running\',
            emails_sent INT DEFAULT 0,
            emails_failed INT DEFAULT 0,
            push_sent INT DEFAULT 0,
            error_message TEXT DEFAULT NULL,
            triggered_by ENUM(\'cron\', \'manual\', \'unknown\') NOT NULL DEFAULT \'unknown\',
            INDEX idx_job (job_name),
            INDEX idx_started (started_at),
            INDEX idx_status (status)
        ) ENGINE=InnoDB');
    }

    /**
     * GET /cron/status — return cron job status and recent logs.
     */
    public static function status(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        self::ensureTable($db);

        // Get cron settings
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute(['cronSettings']);
        $row = $stmt->fetch();
        $cronSettings = $row ? json_decode($row['setting_value'], true) : self::defaultSettings();

        // Get last 20 executions
        $logs = $db->query('SELECT * FROM cron_logs WHERE job_name = \'send-reminders\' ORDER BY started_at DESC LIMIT 20')->fetchAll();

        // Determine health status
        $lastRun = !empty($logs) ? $logs[0] : null;
        $health = 'unknown';
        if ($lastRun) {
            if ($lastRun['status'] === 'running') {
                // Check if it's been running for more than 5 minutes (stuck)
                $startedAt = strtotime($lastRun['started_at']);
                $health = (time() - $startedAt > 300) ? 'stuck' : 'running';
            } elseif ($lastRun['status'] === 'success') {
                $health = 'healthy';
            } else {
                $health = 'error';
            }
        }

        // Calculate next run based on frequency
        $nextRun = null;
        if ($lastRun && $cronSettings['enabled']) {
            $lastTime = strtotime($lastRun['started_at']);
            $freqMinutes = $cronSettings['frequencyMinutes'] ?? 60;
            $nextRun = date('Y-m-d H:i:s', $lastTime + ($freqMinutes * 60));
        }

        Response::json([
            'settings' => $cronSettings,
            'health' => $health,
            'lastRun' => $lastRun ? [
                'id' => $lastRun['id'],
                'startedAt' => $lastRun['started_at'],
                'finishedAt' => $lastRun['finished_at'],
                'durationMs' => (int) $lastRun['duration_ms'],
                'status' => $lastRun['status'],
                'emailsSent' => (int) $lastRun['emails_sent'],
                'emailsFailed' => (int) $lastRun['emails_failed'],
                'pushSent' => (int) $lastRun['push_sent'],
                'error' => $lastRun['error_message'],
                'triggeredBy' => $lastRun['triggered_by'],
            ] : null,
            'nextRun' => $nextRun,
            'recentLogs' => array_map(function ($log) {
                return [
                    'id' => $log['id'],
                    'startedAt' => $log['started_at'],
                    'finishedAt' => $log['finished_at'],
                    'durationMs' => (int) $log['duration_ms'],
                    'status' => $log['status'],
                    'emailsSent' => (int) $log['emails_sent'],
                    'emailsFailed' => (int) $log['emails_failed'],
                    'pushSent' => (int) $log['push_sent'],
                    'error' => $log['error_message'],
                    'triggeredBy' => $log['triggered_by'],
                ];
            }, $logs),
        ]);
    }

    /**
     * PUT /cron/settings — update cron job configuration.
     */
    public static function updateSettings(array $body): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();

        // Validate frequency
        $validFrequencies = [5, 15, 30, 60, 120, 360, 720, 1440];
        $freq = (int) ($body['frequencyMinutes'] ?? 60);
        if (!in_array($freq, $validFrequencies)) {
            Response::error('Invalid frequency. Allowed: ' . implode(', ', $validFrequencies), 400);
            return;
        }

        $settings = [
            'enabled' => (bool) ($body['enabled'] ?? true),
            'frequencyMinutes' => $freq,
            'runHour' => max(0, min(23, (int) ($body['runHour'] ?? 8))),
            'runMinute' => max(0, min(59, (int) ($body['runMinute'] ?? 0))),
        ];

        $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        $stmt->execute(['cronSettings', json_encode($settings)]);

        Response::json(['success' => true, 'settings' => $settings]);
    }

    /**
     * POST /cron/run — manually trigger the cron job.
     */
    public static function manualRun(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        self::ensureTable($db);

        // Start a log entry
        $logId = self::startLog($db, 'send-reminders', 'manual');

        $startTime = microtime(true);
        $error = null;
        $sentCount = 0;
        $failCount = 0;
        $pushCount = 0;

        try {
            // Capture output from the reminder script
            ob_start();

            // Set globals that the cron script expects
            $_GET['token'] = Env::get('CRON_SECRET', '');

            // Include the cron script
            require __DIR__ . '/../cron/send-reminders.php';

            $output = ob_get_clean();

            // Parse counts from output if available
            if (preg_match('/Sent:\s*(\d+)/', $output, $m)) $sentCount = (int) $m[1];
            if (preg_match('/Failed:\s*(\d+)/', $output, $m)) $failCount = (int) $m[1];

        } catch (\Throwable $e) {
            ob_end_clean();
            $error = $e->getMessage();
        }

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        // Finish log entry
        self::finishLog($db, $logId, $error ? 'error' : 'success', $sentCount, $failCount, $pushCount, $durationMs, $error);

        Response::json([
            'success' => !$error,
            'duration_ms' => $durationMs,
            'emails_sent' => $sentCount,
            'emails_failed' => $failCount,
            'error' => $error,
        ]);
    }

    /**
     * DELETE /cron/logs — clear old log entries (keep last 50).
     */
    public static function clearLogs(): void {
        AuthMiddleware::verify();
        $db = Database::getInstance();
        self::ensureTable($db);

        // Keep last 50 entries, delete the rest
        $db->exec('DELETE FROM cron_logs WHERE id NOT IN (SELECT id FROM (SELECT id FROM cron_logs ORDER BY started_at DESC LIMIT 50) AS keep)');

        Response::success(['message' => 'Old log entries cleared']);
    }

    // ── Logging helpers (used by both manual runs and the cron script) ──

    public static function startLog(\PDO $db, string $jobName, string $triggeredBy = 'unknown'): int {
        self::ensureTable($db);
        $stmt = $db->prepare('INSERT INTO cron_logs (job_name, triggered_by) VALUES (?, ?)');
        $stmt->execute([$jobName, $triggeredBy]);
        return (int) $db->lastInsertId();
    }

    public static function finishLog(\PDO $db, int $logId, string $status, int $emailsSent = 0, int $emailsFailed = 0, int $pushSent = 0, int $durationMs = 0, ?string $error = null): void {
        $stmt = $db->prepare('UPDATE cron_logs SET finished_at = NOW(), status = ?, emails_sent = ?, emails_failed = ?, push_sent = ?, duration_ms = ?, error_message = ? WHERE id = ?');
        $stmt->execute([$status, $emailsSent, $emailsFailed, $pushSent, $durationMs, $error, $logId]);
    }

    public static function defaultSettings(): array {
        return [
            'enabled' => true,
            'frequencyMinutes' => 60,
            'runHour' => 8,
            'runMinute' => 0,
        ];
    }
}
