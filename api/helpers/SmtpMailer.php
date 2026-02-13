<?php
/**
 * Minimal SMTP mailer — no Composer dependencies.
 * Supports STARTTLS, LOGIN/PLAIN auth.
 */
class SmtpMailer {

    public static function send(array $config, string $to, string $subject, string $body): array {
        $host     = $config['host'] ?? '';
        $port     = (int) ($config['port'] ?? 587);
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $fromEmail = $config['fromEmail'] ?? $username;
        $fromName  = $config['fromName'] ?? 'SmartRecur';
        $encryption = $config['encryption'] ?? 'tls'; // tls, ssl, none

        if (!$host || !$username || !$password) {
            return ['success' => false, 'error' => 'SMTP not configured: host, username, and password are required.'];
        }

        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);

            if ($encryption === 'ssl') {
                $socket = stream_socket_client(
                    "ssl://{$host}:{$port}",
                    $errno, $errstr, 15,
                    STREAM_CLIENT_CONNECT,
                    $context
                );
            } else {
                $socket = stream_socket_client(
                    "tcp://{$host}:{$port}",
                    $errno, $errstr, 15,
                    STREAM_CLIENT_CONNECT,
                    $context
                );
            }

            if (!$socket) {
                return ['success' => false, 'error' => "Connection failed: {$errstr} ({$errno})"];
            }

            $response = self::readLine($socket);
            if (strpos($response, '220') !== 0) {
                fclose($socket);
                return ['success' => false, 'error' => "Unexpected greeting: {$response}"];
            }

            // EHLO
            self::sendCmd($socket, "EHLO " . gethostname());
            $ehloResponse = self::readMultiLine($socket);

            // STARTTLS for port 587
            if ($encryption === 'tls') {
                self::sendCmd($socket, "STARTTLS");
                $tlsResponse = self::readLine($socket);
                if (strpos($tlsResponse, '220') !== 0) {
                    fclose($socket);
                    return ['success' => false, 'error' => "STARTTLS failed: {$tlsResponse}"];
                }

                $cryptoResult = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                if (!$cryptoResult) {
                    fclose($socket);
                    return ['success' => false, 'error' => 'TLS negotiation failed'];
                }

                // Re-EHLO after STARTTLS
                self::sendCmd($socket, "EHLO " . gethostname());
                self::readMultiLine($socket);
            }

            // AUTH LOGIN
            self::sendCmd($socket, "AUTH LOGIN");
            $authResponse = self::readLine($socket);
            if (strpos($authResponse, '334') !== 0) {
                fclose($socket);
                return ['success' => false, 'error' => "AUTH failed: {$authResponse}"];
            }

            self::sendCmd($socket, base64_encode($username));
            $userResponse = self::readLine($socket);
            if (strpos($userResponse, '334') !== 0) {
                fclose($socket);
                return ['success' => false, 'error' => "Username rejected: {$userResponse}"];
            }

            self::sendCmd($socket, base64_encode($password));
            $passResponse = self::readLine($socket);
            if (strpos($passResponse, '235') !== 0) {
                fclose($socket);
                return ['success' => false, 'error' => "Authentication failed. Check credentials."];
            }

            // MAIL FROM
            self::sendCmd($socket, "MAIL FROM:<{$fromEmail}>");
            $fromResponse = self::readLine($socket);
            if (strpos($fromResponse, '250') !== 0) {
                fclose($socket);
                return ['success' => false, 'error' => "MAIL FROM rejected: {$fromResponse}"];
            }

            // RCPT TO
            self::sendCmd($socket, "RCPT TO:<{$to}>");
            $rcptResponse = self::readLine($socket);
            if (strpos($rcptResponse, '250') !== 0) {
                fclose($socket);
                return ['success' => false, 'error' => "RCPT TO rejected: {$rcptResponse}"];
            }

            // DATA
            self::sendCmd($socket, "DATA");
            $dataResponse = self::readLine($socket);
            if (strpos($dataResponse, '354') !== 0) {
                fclose($socket);
                return ['success' => false, 'error' => "DATA rejected: {$dataResponse}"];
            }

            // Build email headers + body
            $encodedFrom = "=?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>";
            $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
            $messageId = '<' . bin2hex(random_bytes(16)) . '@' . gethostname() . '>';

            $message = "From: {$encodedFrom}\r\n";
            $message .= "To: {$to}\r\n";
            $message .= "Subject: {$encodedSubject}\r\n";
            $message .= "Message-ID: {$messageId}\r\n";
            $message .= "Date: " . date('r') . "\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n";
            $message .= "\r\n";
            // Escape lines starting with a dot (SMTP transparency)
            $message .= str_replace("\r\n.", "\r\n..", $body);
            $message .= "\r\n.\r\n";

            fwrite($socket, $message);
            $sendResponse = self::readLine($socket);

            // QUIT
            self::sendCmd($socket, "QUIT");
            fclose($socket);

            if (strpos($sendResponse, '250') === 0) {
                return ['success' => true, 'error' => ''];
            } else {
                return ['success' => false, 'error' => "Send failed: {$sendResponse}"];
            }

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'SMTP error: ' . $e->getMessage()];
        }
    }

    /**
     * Test SMTP connection without sending an email.
     */
    public static function test(array $config): array {
        $host     = $config['host'] ?? '';
        $port     = (int) ($config['port'] ?? 587);
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $encryption = $config['encryption'] ?? 'tls';

        if (!$host || !$username || !$password) {
            return [false, 'SMTP not configured: host, username, and password are required.'];
        }

        try {
            $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);

            if ($encryption === 'ssl') {
                $socket = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
            } else {
                $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
            }

            if (!$socket) {
                return [false, "Connection to {$host}:{$port} failed: {$errstr}"];
            }

            $greeting = self::readLine($socket);
            if (strpos($greeting, '220') !== 0) {
                fclose($socket);
                return [false, "Unexpected server response: {$greeting}"];
            }

            self::sendCmd($socket, "EHLO " . gethostname());
            self::readMultiLine($socket);

            if ($encryption === 'tls') {
                self::sendCmd($socket, "STARTTLS");
                $tlsResp = self::readLine($socket);
                if (strpos($tlsResp, '220') !== 0) {
                    fclose($socket);
                    return [false, "STARTTLS not supported: {$tlsResp}"];
                }
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                if (!$crypto) {
                    fclose($socket);
                    return [false, 'TLS negotiation failed'];
                }
                self::sendCmd($socket, "EHLO " . gethostname());
                self::readMultiLine($socket);
            }

            // Try AUTH
            self::sendCmd($socket, "AUTH LOGIN");
            self::readLine($socket);
            self::sendCmd($socket, base64_encode($username));
            self::readLine($socket);
            self::sendCmd($socket, base64_encode($password));
            $authResp = self::readLine($socket);

            self::sendCmd($socket, "QUIT");
            fclose($socket);

            if (strpos($authResp, '235') === 0) {
                return [true, "SMTP connected and authenticated as {$username}"];
            } else {
                return [false, "Authentication failed. Check username/password."];
            }
        } catch (\Exception $e) {
            return [false, 'SMTP test error: ' . $e->getMessage()];
        }
    }

    private static function sendCmd($socket, string $cmd): void {
        fwrite($socket, $cmd . "\r\n");
    }

    private static function readLine($socket): string {
        $line = '';
        $timeout = time() + 10;
        while (time() < $timeout) {
            $data = fgets($socket, 512);
            if ($data === false) break;
            $line .= $data;
            if (strlen($data) >= 2 && substr($data, -2) === "\r\n") break;
        }
        return trim($line);
    }

    private static function readMultiLine($socket): string {
        $response = '';
        $timeout = time() + 10;
        while (time() < $timeout) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $response .= $line;
            // Multi-line responses have a dash after the code (e.g. "250-")
            // The last line has a space (e.g. "250 ")
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return trim($response);
    }
}
