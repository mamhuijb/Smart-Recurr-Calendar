<?php

class AuthController {

    public static function login(array $body): void {
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        if (!$username || !$password) {
            Response::error('Username and password are required', 400);
        }

        // Rate limiting: 5 attempts per 5 minutes per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateKey = "login:{$ip}";
        if (!RateLimiter::check($rateKey)) {
            Response::error('Too many login attempts. Please wait 5 minutes.', 429);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            RateLimiter::hit($rateKey);
            Response::error('Invalid credentials', 401);
        }

        // Successful login — clear rate limit
        RateLimiter::clear($rateKey);

        // Check 2FA
        if ($user['two_factor_enabled'] && $user['two_factor_secret']) {
            $config = require __DIR__ . '/../config/app.php';
            $tempToken = JWT::encode([
                'user_id' => $user['id'],
                'type'    => '2fa_pending',
                'exp'     => time() + 300,
            ], $config['jwt_secret']);

            Response::json([
                'requires_2fa' => true,
                'temp_token'   => $tempToken,
            ]);
        }

        // No 2FA — issue full token
        self::issueToken($user);
    }

    public static function verify2FA(array $body): void {
        $tempToken = $body['temp_token'] ?? '';
        $code      = $body['code'] ?? '';

        if (!$tempToken || !$code) {
            Response::error('Token and code are required', 400);
        }

        $config  = require __DIR__ . '/../config/app.php';
        $payload = JWT::decode($tempToken, $config['jwt_secret']);

        if (!$payload || ($payload['type'] ?? '') !== '2fa_pending') {
            Response::error('Invalid or expired 2FA session', 401);
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$payload['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('User not found', 404);
        }

        if (!self::verifyTOTP($code, $user['two_factor_secret'])) {
            Response::error('Invalid 2FA code', 401);
        }

        self::issueToken($user);
    }

    public static function me(): void {
        $auth = AuthMiddleware::verify();

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id, username, two_factor_enabled FROM users WHERE id = ?');
        $stmt->execute([$auth['user_id']]);
        $user = $stmt->fetch();

        if (!$user) Response::error('User not found', 404);

        Response::json(['user' => $user]);
    }

    // ── Private helpers ─────────────────────────────────────

    private static function issueToken(array $user): void {
        $config = require __DIR__ . '/../config/app.php';
        $token  = JWT::encode([
            'user_id'  => $user['id'],
            'username' => $user['username'],
            'exp'      => time() + $config['jwt_expiry'],
        ], $config['jwt_secret']);

        Response::json([
            'token' => $token,
            'user'  => [
                'id'       => $user['id'],
                'username' => $user['username'],
            ],
        ]);
    }

    /**
     * TOTP verification (RFC 6238) — no external dependencies.
     */
    private static function verifyTOTP(string $code, string $secret): bool {
        $secretBytes = self::base32Decode($secret);
        $timeStep    = intdiv(time(), 30);

        for ($i = -1; $i <= 1; $i++) {
            $t    = $timeStep + $i;
            $time = pack('N*', 0) . pack('N*', $t);
            $hash = hash_hmac('sha1', $time, $secretBytes, true);

            $offset = ord($hash[19]) & 0x0f;
            $otp = (
                ((ord($hash[$offset])     & 0x7f) << 24) |
                ((ord($hash[$offset + 1]) & 0xff) << 16) |
                ((ord($hash[$offset + 2]) & 0xff) << 8)  |
                 (ord($hash[$offset + 3]) & 0xff)
            ) % 1000000;

            if (str_pad((string) $otp, 6, '0', STR_PAD_LEFT) === str_pad($code, 6, '0', STR_PAD_LEFT)) {
                return true;
            }
        }
        return false;
    }

    private static function base32Decode(string $input): string {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $c = $input[$i];
            if ($c === '=' || $c === ' ') continue;
            $pos = strpos($alphabet, strtoupper($c));
            if ($pos === false) continue;
            $v = ($v << 5) | $pos;
            $vbits += 5;
            if ($vbits >= 8) {
                $vbits -= 8;
                $output .= chr(($v >> $vbits) & 0xff);
            }
        }
        return $output;
    }
}
