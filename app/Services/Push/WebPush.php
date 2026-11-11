<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Pengirim Web Push standar (RFC 8030) dengan otentikasi VAPID (RFC 8292) dan enkripsi isi aes128gcm
 * (RFC 8291), memakai OpenSSL bawaan PHP. Berlaku untuk Chrome/Edge/Android (FCM), Firefox (Mozilla),
 * dan Safari macOS/iOS 16.4+ (Apple, untuk aplikasi yang ditambahkan ke Layar Utama).
 */
class WebPush
{
    public const SENT = 'sent';

    /** Langganan sudah tidak berlaku (404/410): baris dihapus. */
    public const GONE = 'gone';

    public const FAILED = 'failed';

    private const RECORD_SIZE = 4096;

    private const P256_SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public function __construct(
        private readonly string $publicKey,
        private readonly string $privateKey,
        private readonly string $subject,
    ) {
    }

    public static function fromConfig(): ?self
    {
        $public = (string) config('normchat.push.public_key');
        $private = (string) config('normchat.push.private_key');

        return $public !== '' && $private !== '' ? new self($public, $private, (string) config('normchat.push.subject')) : null;
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * @param  array<string, mixed>  $payload  dibaca service worker (resources/views/pwa/sw.blade.php)
     * @param  string|null  $topic  pesan tertunda dengan topik sama diganti yang terbaru (maks. 32 karakter URL-safe)
     */
    public function send(PushSubscription $subscription, array $payload, int $ttl = 86400, ?string $topic = null, string $urgency = 'high'): string
    {
        try {
            $body = self::encrypt(
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                self::base64UrlDecode($subscription->public_key),
                self::base64UrlDecode($subscription->auth_token),
            );
            $headers = [
                'Authorization' => 'vapid t=' . $this->vapidToken($subscription->endpoint) . ', k=' . $this->publicKey,
                'Content-Encoding' => 'aes128gcm',
                'TTL' => (string) $ttl,
                'Urgency' => $urgency,
            ];
            if ($topic !== null) {
                $headers['Topic'] = substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $topic), 0, 32);
            }

            $response = Http::withHeaders($headers)
                ->withBody($body, 'application/octet-stream')
                ->timeout(10)
                ->post($subscription->endpoint);
        } catch (Throwable $e) {
            Log::warning('Web push request failed.', ['subscription' => $subscription->id, 'error' => $e->getMessage()]);

            return self::FAILED;
        }

        if ($response->successful()) {
            return self::SENT;
        }
        if (in_array($response->status(), [404, 410], true)) {
            return self::GONE;
        }

        Log::warning('Web push rejected.', ['subscription' => $subscription->id, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);

        return self::FAILED;
    }

    /**
     * Enkripsi aes128gcm satu record (RFC 8291 §3.4). $salt dan $localKey hanya diisi oleh test (vektor RFC).
     *
     * @param  array{private: string, public: string}|null  $localKey  pasangan kunci pengirim mentah (32 + 65 byte)
     */
    public static function encrypt(string $plaintext, string $userPublicKey, string $authSecret, ?string $salt = null, ?array $localKey = null): string
    {
        if (strlen($userPublicKey) !== 65 || strlen($authSecret) !== 16) {
            throw new RuntimeException('Kunci langganan push tidak valid.');
        }

        $salt ??= random_bytes(16);
        if ($localKey === null) {
            $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
            $details = openssl_pkey_get_details($key);
            $localPublic = "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
        } else {
            $key = openssl_pkey_get_private(self::privateKeyPem($localKey['private'], $localKey['public']));
            $localPublic = $localKey['public'];
        }

        $peer = openssl_pkey_get_public(self::publicKeyPem($userPublicKey));
        $sharedSecret = openssl_pkey_derive($peer, $key, 32);
        if ($sharedSecret === false) {
            throw new RuntimeException('ECDH gagal.');
        }

        $prkKey = hash_hmac('sha256', $sharedSecret, $authSecret, true);
        $ikm = hash_hmac('sha256', "WebPush: info\0" . $userPublicKey . $localPublic . "\x01", $prkKey, true);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);

        $tag = '';
        $cipher = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Enkripsi push gagal.');
        }

        return $salt . pack('N', self::RECORD_SIZE) . chr(strlen($localPublic)) . $localPublic . $cipher . $tag;
    }

    /** JWT VAPID (ES256) untuk asal endpoint, berlaku 12 jam. */
    public function vapidToken(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $audience = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::base64UrlEncode(json_encode(['aud' => $audience, 'exp' => time() + 12 * 3600, 'sub' => $this->subject], JSON_UNESCAPED_SLASHES));
        $input = $header . '.' . $claims;

        $key = openssl_pkey_get_private(self::privateKeyPem(self::base64UrlDecode($this->privateKey), self::base64UrlDecode($this->publicKey)));
        if ($key === false || ! openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Kunci VAPID tidak valid.');
        }

        return $input . '.' . self::base64UrlEncode(self::derToRaw($der));
    }

    /** @return array{public: string, private: string} pasangan kunci VAPID baru (base64url) */
    public static function generateKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($key);
        $public = "\x04" . str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        return [
            'public' => self::base64UrlEncode($public),
            'private' => self::base64UrlEncode(str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    }

    /** Kunci publik P-256 mentah (65 byte) → PEM SubjectPublicKeyInfo. */
    private static function publicKeyPem(string $raw): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::P256_SPKI_PREFIX) . $raw), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** Kunci privat P-256 mentah (d 32 byte + publik 65 byte) → PEM SEC1 "EC PRIVATE KEY". */
    private static function privateKeyPem(string $d, string $public): string
    {
        $der = hex2bin('30770201010420') . str_pad($d, 32, "\0", STR_PAD_LEFT)
            . hex2bin('a00a06082a8648ce3d030107a144034200') . $public;

        return "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
    }

    /** Tanda tangan ECDSA DER → r||s 64 byte (format JWS). */
    private static function derToRaw(string $der): string
    {
        $offset = 2;
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $value = substr($der, $offset + 2, $length);
            $parts[] = str_pad(ltrim($value, "\0"), 32, "\0", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $parts[0] . $parts[1];
    }
}
