<?php
/**
 * SmartRecur API Router
 * PHP backend for Plesk + MariaDB deployment.
 */

// ── Global error handler: ensure ALL errors output JSON, never HTML ──
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't leak HTML errors to client
ini_set('log_errors', '1');

set_error_handler(function (int $severity, string $message, string $file, int $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    $response = ['error' => 'Internal server error'];
    // In development, include details; in production, log only
    if (getenv('APP_DEBUG') === 'true') {
        $response['debug'] = $e->getMessage();
        $response['file'] = $e->getFile() . ':' . $e->getLine();
    }
    error_log('SmartRecur API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode($response);
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        $response = ['error' => 'Fatal server error'];
        if (getenv('APP_DEBUG') === 'true') {
            $response['debug'] = $error['message'];
            $response['file'] = $error['file'] . ':' . $error['line'];
        }
        error_log('SmartRecur FATAL: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
        echo json_encode($response);
    }
});

// Bootstrap – load env early so CORS_ORIGIN is available
require_once __DIR__ . '/helpers/Env.php';
Env::load(dirname(__DIR__) . '/.env');

// CORS – validate origin against configured allowlist
$allowedOrigin = Env::get('CORS_ORIGIN', '*');
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($allowedOrigin === '*') {
    header('Access-Control-Allow-Origin: *');
} elseif ($requestOrigin && $requestOrigin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-HTTP-Method-Override');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/JWT.php';
require_once __DIR__ . '/helpers/UUID.php';
require_once __DIR__ . '/helpers/RateLimiter.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// Parse request URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^/api#', '', $uri);
$uri = rtrim($uri, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// Method override: POST + X-HTTP-Method-Override header → treat as PUT/DELETE
// This bypasses ModSecurity/WAF rules that block PUT/DELETE on Plesk/CloudLinux
if ($method === 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $override = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
    if (in_array($override, ['PUT', 'DELETE', 'PATCH'], true)) {
        $method = $override;
    }
}

// Request body
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Simple router
$matched = false;

function route(string $routeMethod, string $pattern, callable $handler): void {
    global $uri, $method, $body, $matched;
    if ($matched || $method !== $routeMethod) return;

    $regex = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $pattern);
    if (preg_match("#^{$regex}$#", $uri, $matches)) {
        $matched = true;
        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        $handler($body, $params);
    }
}

// ── Health ──────────────────────────────────────────────────
route('GET', '/health', function () {
    $isDebug = getenv('APP_DEBUG') === 'true';
    $checks = ['status' => 'ok', 'version' => '2.0.1'];

    if ($isDebug) {
        $checks['php'] = PHP_VERSION;
        $checks['env'] = Env::get('DB_HOST') ? 'loaded' : 'missing';

        try {
            $db = Database::getInstance();
            $db->query('SELECT 1');
            $checks['database'] = 'connected';
            $checks['tables'] = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            $checks['database'] = 'error';
            $checks['db_error'] = $e->getMessage();
            $checks['status'] = 'degraded';
        }
    } else {
        // Production: only show basic status, no internals
        try {
            $db = Database::getInstance();
            $db->query('SELECT 1');
            $checks['database'] = 'connected';
        } catch (\Exception $e) {
            $checks['database'] = 'error';
            $checks['status'] = 'degraded';
        }
    }

    Response::json($checks);
});

// ── Auth ────────────────────────────────────────────────────
require_once __DIR__ . '/controllers/AuthController.php';
route('POST', '/auth/login',      fn($b, $p) => AuthController::login($b));
route('POST', '/auth/verify-2fa', fn($b, $p) => AuthController::verify2FA($b));
route('GET',  '/auth/me',         fn($b, $p) => AuthController::me());

// ── Events ──────────────────────────────────────────────────
require_once __DIR__ . '/controllers/EventController.php';
route('GET',    '/events',     fn($b, $p) => EventController::index());
route('POST',   '/events',     fn($b, $p) => EventController::store($b));
route('PUT',    '/events/:id', fn($b, $p) => EventController::update($p['id'], $b));
route('DELETE', '/events/:id', fn($b, $p) => EventController::destroy($p['id']));

// ── Customers ───────────────────────────────────────────────
require_once __DIR__ . '/controllers/CustomerController.php';
route('GET',    '/customers',     fn($b, $p) => CustomerController::index());
route('POST',   '/customers',     fn($b, $p) => CustomerController::store($b));
route('PUT',    '/customers/:id', fn($b, $p) => CustomerController::update($p['id'], $b));
route('DELETE', '/customers/:id', fn($b, $p) => CustomerController::destroy($p['id']));

// ── Services ────────────────────────────────────────────────
require_once __DIR__ . '/controllers/ServiceController.php';
route('GET',    '/services',     fn($b, $p) => ServiceController::index());
route('POST',   '/services',     fn($b, $p) => ServiceController::store($b));
route('PUT',    '/services/:id', fn($b, $p) => ServiceController::update($p['id'], $b));
route('DELETE', '/services/:id', fn($b, $p) => ServiceController::destroy($p['id']));

// ── Technicians ─────────────────────────────────────────────
require_once __DIR__ . '/controllers/TechnicianController.php';
route('GET',    '/technicians',     fn($b, $p) => TechnicianController::index());
route('POST',   '/technicians',     fn($b, $p) => TechnicianController::store($b));
route('PUT',    '/technicians/:id', fn($b, $p) => TechnicianController::update($p['id'], $b));
route('DELETE', '/technicians/:id', fn($b, $p) => TechnicianController::destroy($p['id']));

// ── Settings ────────────────────────────────────────────────
require_once __DIR__ . '/controllers/SettingsController.php';
route('GET', '/settings',         fn($b, $p) => SettingsController::index());
route('PUT', '/settings',         fn($b, $p) => SettingsController::update($b));
route('GET', '/settings/backup',  fn($b, $p) => SettingsController::backup());
route('POST', '/settings/restore', fn($b, $p) => SettingsController::restore($b));

// ── Integrations ────────────────────────────────────────────
require_once __DIR__ . '/controllers/IntegrationController.php';
route('GET',  '/integrations/status',              fn($b, $p) => IntegrationController::status());
route('PUT',  '/integrations/:type/config',        fn($b, $p) => IntegrationController::saveConfig($p['type'], $b));
route('POST', '/integrations/:type/test',          fn($b, $p) => IntegrationController::test($p['type']));
route('GET',  '/integrations/office365/auth-url',  fn($b, $p) => IntegrationController::office365AuthUrl());
route('GET',  '/integrations/office365/callback',  fn($b, $p) => IntegrationController::office365Callback());
route('GET',  '/integrations/syncro/customers',    fn($b, $p) => IntegrationController::syncroCustomers());
route('POST', '/integrations/syncro/tickets',      fn($b, $p) => IntegrationController::syncroCreateTicket($b));
route('POST', '/integrations/email/send',          fn($b, $p) => IntegrationController::sendEmail($b));
route('GET',  '/integrations/office365/calendars',  fn($b, $p) => IntegrationController::office365Calendars());
route('POST', '/integrations/office365/calendar-event', fn($b, $p) => IntegrationController::office365CreateEvent($b));
route('GET',  '/email-logs',                            fn($b, $p) => IntegrationController::emailLogs());
route('POST', '/integrations/invoiceninja/sync-customers', fn($b, $p) => IntegrationController::invoiceNinjaSyncCustomers());

// ── Push Notifications ──────────────────────────────────────
require_once __DIR__ . '/controllers/PushController.php';

route('GET',  '/push/config',      fn($b, $p) => PushController::config());
route('POST', '/push/subscribe',   fn($b, $p) => PushController::subscribe($b));
route('POST', '/push/unsubscribe', fn($b, $p) => PushController::unsubscribe($b));
route('POST', '/push/test',        fn($b, $p) => PushController::test());

// ── Cron Management (authenticated) ────────────────────────
require_once __DIR__ . '/controllers/CronController.php';

route('GET',    '/cron/status',   fn($b, $p) => CronController::status());
route('PUT',    '/cron/settings', fn($b, $p) => CronController::updateSettings($b));
route('POST',   '/cron/run',      fn($b, $p) => CronController::manualRun());
route('DELETE', '/cron/logs',     fn($b, $p) => CronController::clearLogs());

// ── Cron Execution (secret-protected) ──────────────────────
route('GET', '/cron/send-reminders', function () {
    $cronSecret = Env::get('CRON_SECRET', '');
    $token = $_GET['token'] ?? '';
    if (!$cronSecret || !hash_equals($cronSecret, $token)) {
        Response::error('Forbidden', 403);
        return;
    }
    require_once __DIR__ . '/cron/send-reminders.php';
});

// ── 404 ─────────────────────────────────────────────────────
if (!$matched) {
    Response::error('Not found', 404);
}
