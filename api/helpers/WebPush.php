<?php
/**
 * WebPush helper — sends Web Push notifications using the Web Push protocol.
 * Uses raw PHP with openssl for VAPID signatures. No Composer dependencies needed.
 * Compatible with Plesk/cPanel PHP hosting.
 *
 * VAPID keys are stored in the database (settings table) or .env file.
 */

class WebPush {

    /**
     * Generate a new VAPID key pair (P-256 ECDSA).
     * Returns ['publicKey' => base64url, 'privateKey' => base64url]
     */
    public static function generateVapidKeys(): array {
        $key = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        $details = openssl_pkey_get_details($key);

        // Extract raw public key (uncompressed point: 04 || x || y)
        $x = $details['ec']['x'];
        $y = $details['ec']['y'];
        $publicKeyRaw = "\x04" . str_pad($x, 32, "\0", STR_PAD_LEFT) . str_pad($y, 32, "\0", STR_PAD_LEFT);

        // Extract raw private key (d parameter)
        $d = $details['ec']['d'];

        openssl_pkey_export($key, $pem);

        return [
            'publicKey'  => self::base64UrlEncode($publicKeyRaw),
            'privateKey' => self::base64UrlEncode(str_pad($d, 32, "\0", STR_PAD_LEFT)),
            'pem'        => $pem,
        ];
    }

    /**
     * Send a push notification to a subscription endpoint.
     *
     * @param array  $subscription  ['endpoint' => '...', 'keys' => ['p256dh' => '...', 'auth' => '...']]
     * @param string $payload       JSON string payload
     * @param string $vapidPublicKey  Base64URL encoded public key
     * @param string $vapidPrivateKey Base64URL encoded private key (32 bytes d)
     * @param string $vapidSubject    mailto: or https:// contact for VAPID
     * @return array ['success' => bool, 'statusCode' => int, 'error' => string]
     */
    public static function send(
        array $subscription,
        string $payload,
        string $vapidPublicKey,
        string $vapidPrivateKey,
        string $vapidSubject = ''
    ): array {
        $endpoint = $subscription['endpoint'] ?? '';
        $p256dh = $subscription['keys']['p256dh'] ?? '';
        $authKey = $subscription['keys']['auth'] ?? '';

        if (!$endpoint || !$p256dh || !$authKey) {
            return ['success' => false, 'statusCode' => 0, 'error' => 'Invalid subscription'];
        }

        try {
            // Encrypt the payload using Web Push Content Encoding (aes128gcm)
            $encrypted = self::encryptPayload($payload, $p256dh, $authKey);
            if (!$encrypted) {
                return ['success' => false, 'statusCode' => 0, 'error' => 'Encryption failed'];
            }

            // Build VAPID authorization header
            $vapidHeaders = self::buildVapidHeaders($endpoint, $vapidPublicKey, $vapidPrivateKey, $vapidSubject);

            // Send HTTP POST to the push service
            $headers = [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Content-Length: ' . strlen($encrypted['cipherText']),
                'TTL: 86400',
                'Urgency: high',
                $vapidHeaders['authorization'],
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $encrypted['cipherText'],
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
            ]);

            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['success' => false, 'statusCode' => 0, 'error' => "cURL: $error"];
            }

            $success = $statusCode >= 200 && $statusCode < 300;
            return [
                'success'    => $success,
                'statusCode' => $statusCode,
                'error'      => $success ? '' : "HTTP $statusCode: $response",
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'statusCode' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Encrypt payload for Web Push (aes128gcm content encoding).
     * Simplified implementation using openssl.
     */
    private static function encryptPayload(string $payload, string $userPublicKey, string $userAuth): ?array {
        $userPublicKeyRaw = self::base64UrlDecode($userPublicKey);
        $userAuthRaw = self::base64UrlDecode($userAuth);

        if (strlen($userPublicKeyRaw) !== 65 || strlen($userAuthRaw) !== 16) {
            return null;
        }

        // Generate local ECDH key pair
        $localKey = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $localDetails = openssl_pkey_get_details($localKey);
        $localPublicRaw = "\x04" . str_pad($localDetails['ec']['x'], 32, "\0", STR_PAD_LEFT)
                                 . str_pad($localDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);

        // ECDH shared secret
        $sharedSecret = self::ecdhSecret($localKey, $userPublicKeyRaw);
        if (!$sharedSecret) return null;

        // Salt for aes128gcm
        $salt = random_bytes(16);

        // HKDF-based key derivation (RFC 8291)
        // IKM = HKDF(auth, sharedSecret, "WebPush: info\0" || client_public || server_public)
        $authInfo = "WebPush: info\0" . $userPublicKeyRaw . $localPublicRaw;
        $prk = hash_hmac('sha256', $sharedSecret, $userAuthRaw, true);
        $ikm = self::hkdfExpand($prk, $authInfo, 32);

        // Derive content encryption key and nonce
        $prkCEK = hash_hmac('sha256', $ikm, $salt, true);
        $cek = self::hkdfExpand($prkCEK, "Content-Encoding: aes128gcm\0\x01", 16);
        $nonce = self::hkdfExpand($prkCEK, "Content-Encoding: nonce\0\x01", 12);

        // Pad the payload (add \x02 delimiter)
        $paddedPayload = $payload . "\x02";

        // Encrypt with AES-128-GCM
        $tag = '';
        $encrypted = openssl_encrypt($paddedPayload, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($encrypted === false) return null;

        // Build aes128gcm header: salt(16) + rs(4) + idlen(1) + keyid(65)
        $rs = pack('N', 4096);
        $idLen = chr(65);
        $header = $salt . $rs . $idLen . $localPublicRaw;

        return [
            'cipherText' => $header . $encrypted . $tag,
        ];
    }

    /**
     * Compute ECDH shared secret using openssl.
     */
    private static function ecdhSecret($localPrivKey, string $peerPublicRaw): ?string {
        $localDetails = openssl_pkey_get_details($localPrivKey);
        $d = $localDetails['ec']['d'];

        // Build PEM for the peer public key
        // We need to create an EC key from raw coords for ECDH
        // Use openssl_pkey_derive if available (PHP 7.3+)
        if (function_exists('openssl_pkey_derive')) {
            // Create peer key from raw public point
            $peerKey = self::rawPublicToPem($peerPublicRaw);
            if (!$peerKey) return null;
            $peerKeyResource = openssl_pkey_get_public($peerKey);
            if (!$peerKeyResource) return null;

            $shared = '';
            $result = openssl_pkey_derive($peerKeyResource, $localPrivKey, $shared, 256);
            if (!$result) return null;
            return $shared;
        }

        // Fallback: use gmp for ECDH if available
        return null;
    }

    /**
     * Convert raw uncompressed EC public key to PEM format.
     */
    private static function rawPublicToPem(string $rawKey): ?string {
        if (strlen($rawKey) !== 65 || $rawKey[0] !== "\x04") return null;

        // DER header for P-256 public key
        $derHeader = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
        $der = $derHeader . $rawKey;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    /**
     * Build VAPID Authorization header.
     */
    private static function buildVapidHeaders(
        string $endpoint,
        string $vapidPublicKey,
        string $vapidPrivateKey,
        string $vapidSubject
    ): array {
        $parsed = parse_url($endpoint);
        $audience = $parsed['scheme'] . '://' . $parsed['host'];

        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200, // 12 hours
            'sub' => $vapidSubject ?: 'mailto:admin@smartrecur.local',
        ]));

        $signingInput = "$header.$payload";

        // Sign with ES256 using the private key
        $privateKeyPem = self::vapidPrivateKeyToPem($vapidPrivateKey, $vapidPublicKey);
        $key = openssl_pkey_get_private($privateKeyPem);
        if (!$key) {
            return ['authorization' => ''];
        }

        openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);

        // Convert DER signature to raw R||S format
        $rawSig = self::derToRaw($signature);

        $jwt = $signingInput . '.' . self::base64UrlEncode($rawSig);

        return [
            'authorization' => "Authorization: vapid t=$jwt, k=$vapidPublicKey",
        ];
    }

    /**
     * Convert VAPID private key (base64url d value) + public key to PEM.
     */
    private static function vapidPrivateKeyToPem(string $privateKeyB64, string $publicKeyB64): string {
        $d = self::base64UrlDecode($privateKeyB64);
        $publicRaw = self::base64UrlDecode($publicKeyB64);

        // Build DER for EC private key (SEC1 format with public key)
        $x = substr($publicRaw, 1, 32);
        $y = substr($publicRaw, 33, 32);

        // SEC1 EC private key format
        $derPrivKey = "\x30" . chr(119)
            . "\x02\x01\x01"  // version
            . "\x04\x20" . $d // private key
            . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07" // OID prime256v1
            . "\xa1\x44\x03\x42\x00" . $publicRaw; // public key

        $pem = "-----BEGIN EC PRIVATE KEY-----\n"
             . chunk_split(base64_encode($derPrivKey), 64, "\n")
             . "-----END EC PRIVATE KEY-----\n";

        return $pem;
    }

    /**
     * Convert DER-encoded ECDSA signature to raw R||S (64 bytes).
     */
    private static function derToRaw(string $der): string {
        $offset = 3; // skip 30 len 02
        $rLen = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $rLen);
        $offset += $rLen + 1; // skip 02
        $sLen = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $sLen);

        // Pad/trim to 32 bytes each
        $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);

        return substr($r, -32) . substr($s, -32);
    }

    /**
     * HKDF-Expand (RFC 5869) — single-step expansion.
     */
    private static function hkdfExpand(string $prk, string $info, int $length): string {
        $t = '';
        $output = '';
        $counter = 1;
        while (strlen($output) < $length) {
            $t = hash_hmac('sha256', $t . $info . chr($counter), $prk, true);
            $output .= $t;
            $counter++;
        }
        return substr($output, 0, $length);
    }

    // ── Base64URL helpers ────────────────────────────────────────

    public static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
