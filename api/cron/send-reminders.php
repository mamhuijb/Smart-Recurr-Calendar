<?php
/**
 * Cron job: Send reminder emails for upcoming appointments.
 *
 * This script should be called periodically (e.g. every hour or daily) via a server cron job:
 *   php /path/to/api/cron/send-reminders.php
 *
 * Or via HTTP with a secret token:
 *   GET /api/cron/send-reminders.php?token=YOUR_CRON_SECRET
 *
 * It checks all scheduled events against the configured reminder days,
 * sends emails to customers, and logs all attempts to prevent duplicates.
 */

// Bootstrap
require_once __DIR__ . '/../helpers/Env.php';
Env::load(dirname(__DIR__, 2) . '/.env');

// Security: Verify cron secret token when called via HTTP
if (php_sapi_name() !== 'cli') {
    $cronSecret = Env::get('CRON_SECRET', '');
    $providedToken = $_GET['token'] ?? '';

    if (!$cronSecret || !hash_equals($cronSecret, $providedToken)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/UUID.php';
require_once __DIR__ . '/../helpers/SmtpMailer.php';
require_once __DIR__ . '/../integrations/Office365.php';
require_once __DIR__ . '/../controllers/PushController.php';

$db = Database::getInstance();

// Ensure reminder_log table exists (tracks sent reminders to avoid duplicates)
$db->exec('CREATE TABLE IF NOT EXISTS reminder_log (
    id VARCHAR(36) PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL,
    target_date DATE NOT NULL,
    reminder_day INT NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    status ENUM(\'sent\', \'failed\') NOT NULL DEFAULT \'sent\',
    method VARCHAR(20) DEFAULT NULL,
    error TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_date_day (event_id, target_date, reminder_day),
    INDEX idx_created (created_at)
) ENGINE=InnoDB');

// Ensure email_logs table exists
$db->exec('CREATE TABLE IF NOT EXISTS email_logs (
    id VARCHAR(36) PRIMARY KEY,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    status ENUM(\'sent\', \'failed\') NOT NULL DEFAULT \'sent\',
    method VARCHAR(20) NOT NULL DEFAULT \'smtp\',
    error TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB');

// Load settings
$settingsRows = $db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
$settings = [];
foreach ($settingsRows as $row) {
    $settings[$row['setting_key']] = json_decode($row['setting_value'], true);
}

$globalReminderDays = $settings['reminders']['days'] ?? [14, 7, 1];
$globalTemplate = $settings['templates']['reminder'] ?? [
    'subject' => 'Appointment: {service_name} - {date}',
    'body'    => 'Hi {customer_name}, your appointment for {service_name} is scheduled on {date}.',
];
$preferredMailMethod = $settings['preferredMailMethod'] ?? 'auto';

// Load mail configs
$smtpStmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
$smtpStmt->execute(['smtp']);
$smtpRow = $smtpStmt->fetch();
$smtpConfig = $smtpRow ? json_decode($smtpRow['config'], true) : [];
$smtpReady = !empty($smtpConfig['host']) && !empty($smtpConfig['username']) && !empty($smtpConfig['password']);

$o365Stmt = $db->prepare('SELECT config FROM integration_configs WHERE id = ?');
$o365Stmt->execute(['office365']);
$o365Row = $o365Stmt->fetch();
$o365Config = $o365Row ? json_decode($o365Row['config'], true) : [];
// Refresh O365 token if expired
if (!empty($o365Config['accessToken'])) {
    Office365Integration::ensureValidToken($o365Config, $db);
}
$o365Ready = !empty($o365Config['accessToken']);

// Determine send method
$tryOrder = [];
if ($preferredMailMethod === 'smtp')        $tryOrder = ['smtp'];
elseif ($preferredMailMethod === 'office365') $tryOrder = ['office365'];
else                                         $tryOrder = ['smtp', 'office365'];

if (empty($tryOrder) || (!$smtpReady && !$o365Ready)) {
    logOutput("No email method configured. Exiting.");
    exit;
}

// Load events, customers, services
$events = $db->query('SELECT * FROM events WHERE status = \'SCHEDULED\'')->fetchAll();
$customers = $db->query('SELECT * FROM customers')->fetchAll();
$services = $db->query('SELECT * FROM services')->fetchAll();
$technicians = $db->query('SELECT * FROM technicians')->fetchAll();

// Index by ID
$customerMap = [];
foreach ($customers as $c) $customerMap[$c['id']] = $c;
$serviceMap = [];
foreach ($services as $s) $serviceMap[$s['id']] = $s;
$techMap = [];
foreach ($technicians as $t) $techMap[$t['id']] = $t;

$timezone = Env::get('TIMEZONE', 'Europe/Amsterdam');
$today = new DateTimeImmutable('today', new DateTimeZone($timezone));
$sentCount = 0;
$failCount = 0;

foreach ($events as $event) {
    $dates = json_decode($event['generated_dates'], true) ?? [];
    $customerId = $event['customer_id'];
    $serviceId = $event['service_id'];

    $customer = $customerMap[$customerId] ?? null;
    $service = $serviceMap[$serviceId] ?? null;
    if (!$customer || !$service) continue;

    $customerEmail = $customer['email'] ?? '';
    if (!$customerEmail || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) continue;

    // Get per-service reminder days or fall back to global
    $reminderDays = null;
    if (!empty($service['reminder_days'])) {
        $decoded = json_decode($service['reminder_days'], true);
        if (is_array($decoded) && count($decoded) > 0) {
            $reminderDays = $decoded;
        }
    }
    if (empty($reminderDays)) {
        $reminderDays = $globalReminderDays;
    }

    // Get template
    $template = null;
    if (!empty($service['email_template_subject']) || !empty($service['email_template_body'])) {
        $template = [
            'subject' => $service['email_template_subject'] ?? '',
            'body'    => $service['email_template_body'] ?? '',
        ];
    }
    if (empty($template) || empty($template['body'])) {
        $template = $globalTemplate;
    }

    $techName = 'N/A';
    if (!empty($event['technician_id']) && isset($techMap[$event['technician_id']])) {
        $techName = $techMap[$event['technician_id']]['name'];
    }

    foreach ($dates as $dateStr) {
        $eventDate = new DateTimeImmutable($dateStr, new DateTimeZone($timezone));
        $diffDays = (int) $today->diff($eventDate)->format('%r%a');

        // Only future or today
        if ($diffDays < 0) continue;

        foreach ($reminderDays as $reminderDay) {
            if ($diffDays !== $reminderDay && !($diffDays === 0 && in_array(0, $reminderDays))) {
                continue;
            }

            // Check if already sent
            $checkStmt = $db->prepare('SELECT id FROM reminder_log WHERE event_id = ? AND target_date = ? AND reminder_day = ?');
            $checkStmt->execute([$event['id'], $dateStr, $reminderDay]);
            if ($checkStmt->fetch()) continue;

            // Prepare email content
            $subject = $template['subject'];
            $body = $template['body'];
            $replacements = [
                '{customer_name}' => $customer['name'] ?? '',
                '{service_name}'  => $service['name'] ?? '',
                '{date}'          => $dateStr,
                '{company_name}'  => $customer['company'] ?? '',
                '{tech_name}'     => $techName,
                '{location_type}' => $event['location_type'] === 'ON_SITE' ? 'Op locatie' : 'Remote',
                '{link}'          => '', // No confirmation link yet
            ];
            foreach ($replacements as $key => $val) {
                $subject = str_replace($key, $val, $subject);
                $body = str_replace($key, $val, $body);
            }

            // Send email
            $method = '';
            $success = false;
            $error = '';

            foreach ($tryOrder as $try) {
                if ($try === 'smtp' && $smtpReady) {
                    $result = SmtpMailer::send($smtpConfig, $customerEmail, $subject, $body);
                    $method = 'smtp';
                    $success = $result['success'];
                    $error = $result['error'] ?? '';
                    break;
                } elseif ($try === 'office365' && $o365Ready) {
                    $result = Office365Integration::sendMail($o365Config, $customerEmail, $subject, $body);
                    $method = 'office365';
                    $success = $result['success'];
                    $error = $result['error'] ?? '';
                    break;
                }
            }

            if (!$method) continue;

            // Log to reminder_log
            $logId = UUID::v4();
            $logStmt = $db->prepare('INSERT INTO reminder_log (id, event_id, target_date, reminder_day, recipient, status, method, error) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $logStmt->execute([$logId, $event['id'], $dateStr, $reminderDay, $customerEmail, $success ? 'sent' : 'failed', $method, $error ?: null]);

            // Log to email_logs
            $emailLogId = UUID::v4();
            $emailLogStmt = $db->prepare('INSERT INTO email_logs (id, recipient, subject, status, method, error) VALUES (?, ?, ?, ?, ?, ?)');
            $emailLogStmt->execute([$emailLogId, $customerEmail, $subject, $success ? 'sent' : 'failed', $method, $error ?: null]);

            if ($success) {
                $sentCount++;
                logOutput("SENT: {$customerEmail} — {$subject} (event {$event['id']}, {$reminderDay}d before)");
            } else {
                $failCount++;
                logOutput("FAIL: {$customerEmail} — {$error} (event {$event['id']}, {$reminderDay}d before)");
            }

            // Send push notification alongside email
            try {
                $pushResult = PushController::sendReminder(
                    $db,
                    $customer['name'] ?? $customer['company'] ?? 'Client',
                    $service['name'] ?? 'Appointment',
                    $dateStr,
                    $event['start_time'] ?? '09:00',
                    $event['id']
                );
                if ($pushResult['sent'] > 0) {
                    logOutput("PUSH: Sent to {$pushResult['sent']} device(s) for event {$event['id']}");
                }
            } catch (\Exception $e) {
                logOutput("PUSH FAIL: " . $e->getMessage());
            }
        }
    }
}

logOutput("Done. Sent: {$sentCount}, Failed: {$failCount}");

// Output to console (CLI) or JSON (HTTP)
function logOutput(string $msg): void {
    static $messages = [];
    $messages[] = $msg;

    if (php_sapi_name() === 'cli') {
        echo date('[Y-m-d H:i:s] ') . $msg . "\n";
    } else {
        // Will be output at the end via register_shutdown_function
    }
}

// For HTTP calls, output JSON summary at end
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'sent'    => $sentCount,
        'failed'  => $failCount,
    ]);
}
