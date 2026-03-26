<?php

class JWT {
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function encode(array $payload, string $secret): string {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['iat'] = $payload['iat'] ?? time();
        $payload['exp'] = $payload['exp'] ?? time() + 86400;
        $payloadJson = json_encode($payload);

        $b64Header = self::base64UrlEncode($header);
        $b64Payload = self::base64UrlEncode($payloadJson);

        $signature = hash_hmac('sha256', "$b64Header.$b64Payload", $secret, true);
        $b64Signature = self::base64UrlEncode($signature);

        return "$b64Header.$b64Payload.$b64Signature";
    }

    public static function decode(string $token, string $secret): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$b64Header, $b64Payload, $b64Signature] = $parts;

        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "$b64Header.$b64Payload", $secret, true)
        );

        if (!hash_equals($expectedSig, $b64Signature)) return null;

        $payload = json_decode(self::base64UrlDecode($b64Payload), true);
        if (!$payload) return null;

        if (isset($payload['exp']) && $payload['exp'] < time()) return null;

        return $payload;
    }
}
