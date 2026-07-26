<?php

namespace App\Services\WebPush;

use RuntimeException;

/**
 * Minimal Web Push (RFC 8291 aes128gcm + VAPID) sender using only OpenSSL/cURL.
 */
class WebPushSender
{
    public function __construct(
        protected ?string $vapidPublicKey = null,
        protected ?string $vapidPrivateKey = null,
        protected ?string $vapidSubject = null,
        protected int $ttl = 2419200,
    ) {
        $this->vapidPublicKey  = $vapidPublicKey  ?? config('webpush.vapid.public_key');
        $this->vapidPrivateKey = $vapidPrivateKey ?? config('webpush.vapid.private_key');
        $this->vapidSubject    = $vapidSubject    ?? config('webpush.vapid.subject');
        $this->ttl             = $ttl ?: (int) config('webpush.ttl', 2419200);
    }

    public function isConfigured(): bool
    {
        return filled($this->vapidPublicKey) && filled($this->vapidPrivateKey);
    }

    /**
     * @param  array{endpoint:string,public_key:?string,auth_token:?string,content_encoding?:string}  $subscription
     * @return array{success:bool,status:int,body:string,expired:bool}
     */
    public function send(array $subscription, string|array $payload, ?int $ttl = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Web Push VAPID keys are not configured.');
        }

        $endpoint = $subscription['endpoint'] ?? '';
        if ($endpoint === '') {
            throw new RuntimeException('Push subscription endpoint is missing.');
        }

        $body = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $payload;
        $ttl  = $ttl ?? $this->ttl;

        $contentEncoding = $subscription['content_encoding'] ?? 'aes128gcm';
        $encrypted       = $this->encrypt($body, $subscription['public_key'] ?? '', $subscription['auth_token'] ?? '', $contentEncoding);

        $jwt = $this->createVapidJwt($endpoint);

        $headers = [
            'TTL: '.$ttl,
            'Content-Type: application/octet-stream',
            'Content-Encoding: '.$encrypted['contentEncoding'],
            'Authorization: vapid t='.$jwt.', k='.$this->vapidPublicKey,
            // Legacy Chrome/FCM dual auth (harmless alongside vapid scheme).
            'Crypto-Key: p256ecdsa='.$this->vapidPublicKey,
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $encrypted['cipherText'],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $responseBody = curl_exec($ch);
        $status       = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error        = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return [
                'success' => false,
                'status'  => 0,
                'body'    => $error ?: 'cURL error',
                'expired' => false,
            ];
        }

        // 404 / 410 = subscription gone
        $expired = in_array($status, [404, 410], true);
        $success = $status >= 200 && $status < 300;

        return [
            'success' => $success,
            'status'  => $status,
            'body'    => (string) $responseBody,
            'expired' => $expired,
        ];
    }

    /**
     * @return array{cipherText:string,contentEncoding:string}
     */
    protected function encrypt(string $payload, string $userPublicKeyB64, string $userAuthB64, string $contentEncoding): array
    {
        if ($contentEncoding !== 'aes128gcm') {
            $contentEncoding = 'aes128gcm';
        }

        $userPublicKey = $this->base64UrlDecode($userPublicKeyB64);
        $userAuth      = $this->base64UrlDecode($userAuthB64);

        if (strlen($userPublicKey) !== 65 || $userPublicKey[0] !== "\x04") {
            throw new RuntimeException('Invalid subscription public key.');
        }
        if (strlen($userAuth) !== 16) {
            throw new RuntimeException('Invalid subscription auth secret.');
        }

        $localKey = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($localKey === false) {
            throw new RuntimeException('Failed to create local ECDH key.');
        }

        $localDetails = openssl_pkey_get_details($localKey);
        $localPublic  = "\x04"
            .str_pad($localDetails['ec']['x'], 32, "\0", STR_PAD_LEFT)
            .str_pad($localDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $userPem = $this->publicKeyToPem($userPublicKey);
        $userKey = openssl_pkey_get_public($userPem);
        if ($userKey === false) {
            throw new RuntimeException('Failed to parse subscription public key.');
        }

        $sharedSecret = openssl_pkey_derive($userKey, $localKey, 32);
        if ($sharedSecret === false) {
            throw new RuntimeException('ECDH derive failed: '.openssl_error_string());
        }

        $salt = random_bytes(16);

        // RFC 8291: IKM via HKDF-Extract/Expand with auth info
        $keyInfo = "WebPush: info\0".$userPublicKey.$localPublic;
        $ikm     = $this->hkdf($userAuth, $sharedSecret, $keyInfo, 32);

        $prk      = $this->hkdfExtract($salt, $ikm);
        $cek      = $this->hkdfExpand($prk, "Content-Encoding: aes128gcm\0", 16);
        $nonce    = $this->hkdfExpand($prk, "Content-Encoding: nonce\0", 12);

        // aes128gcm record: plaintext || 0x02 padding delimiter
        $padded   = $payload."\x02";
        $cipher   = openssl_encrypt($padded, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('AES-GCM encrypt failed.');
        }

        // Header: salt (16) || rs (4 BE) || idlen (1) || keyid (65)
        $rs     = pack('N', 4096);
        $header = $salt.$rs.chr(65).$localPublic;

        return [
            'cipherText'      => $header.$cipher.$tag,
            'contentEncoding' => 'aes128gcm',
        ];
    }

    protected function createVapidJwt(string $endpoint): string
    {
        $origin = $this->endpointOrigin($endpoint);
        $header = $this->base64UrlEncode(json_encode(['alg' => 'ES256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $claims = $this->base64UrlEncode(json_encode([
            'aud' => $origin,
            'exp' => time() + 12 * 3600,
            'sub' => $this->normalizedVapidSubject(),
        ], JSON_UNESCAPED_SLASHES));

        $unsigned  = $header.'.'.$claims;
        $signature = $this->signEs256($unsigned, $this->vapidPrivateKey);

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    protected function normalizedVapidSubject(): string
    {
        $subject = trim((string) ($this->vapidSubject ?: 'mailto:support@example.com'));
        if (str_starts_with($subject, 'mailto:') || str_starts_with($subject, 'https://')) {
            return $subject;
        }

        return 'mailto:'.$subject;
    }

    protected function signEs256(string $data, string $privateKeyB64): string
    {
        $key = $this->loadVapidPrivateKey($privateKeyB64);

        $der = '';
        if (! openssl_sign($data, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('VAPID sign failed.');
        }

        $signature = $this->derToJose($der);

        // Ensure the signature verifies against the public key we advertise in `k=`.
        $publicPem = $this->publicKeyToPem($this->base64UrlDecode($this->vapidPublicKey));
        $publicKey = openssl_pkey_get_public($publicPem);
        if ($publicKey === false || openssl_verify($data, $der, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('VAPID key pair mismatch. Regenerate with: php artisan webpush:vapid');
        }

        return $signature;
    }

    /**
     * @return \OpenSSLAsymmetricKey|resource
     */
    protected function loadVapidPrivateKey(string $privateKeyB64)
    {
        $d = $this->base64UrlDecode($privateKeyB64);
        if (strlen($d) !== 32) {
            throw new RuntimeException('Invalid VAPID private key length.');
        }

        $pub = $this->base64UrlDecode($this->vapidPublicKey);
        if (strlen($pub) !== 65 || $pub[0] !== "\x04") {
            throw new RuntimeException('Invalid VAPID public key.');
        }

        $x = substr($pub, 1, 32);
        $y = substr($pub, 33, 32);

        $pem = $this->ecPrivateKeyToPem($d, $x, $y);
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('Failed to load VAPID private key: '.openssl_error_string());
        }

        $details = openssl_pkey_get_details($key);
        $rebuilt = "\x04"
            .str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)
            .str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        if (! hash_equals($pub, $rebuilt)) {
            throw new RuntimeException('VAPID public/private keys do not match. Regenerate with: php artisan webpush:vapid');
        }

        return $key;
    }

    protected function derToJose(string $der): string
    {
        $offset = 0;
        $length = strlen($der);

        if ($offset >= $length || ord($der[$offset++]) !== 0x30) {
            throw new RuntimeException('Invalid ECDSA signature.');
        }

        [$seqLen, $offset] = $this->readAsn1Length($der, $offset);
        $seqEnd = $offset + $seqLen;

        if ($offset >= $length || ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Invalid ECDSA R.');
        }
        [$rLen, $offset] = $this->readAsn1Length($der, $offset);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        if ($offset >= $length || ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Invalid ECDSA S.');
        }
        [$sLen, $offset] = $this->readAsn1Length($der, $offset);
        $s = substr($der, $offset, $sLen);

        if ($offset + $sLen > $seqEnd && $seqLen > 0) {
            // Allow openssl quirks; still normalize R/S.
        }

        $r = ltrim($r, "\0");
        $s = ltrim($s, "\0");
        $r = str_pad($r, 32, "\0", STR_PAD_LEFT);
        $s = str_pad($s, 32, "\0", STR_PAD_LEFT);

        if (strlen($r) !== 32 || strlen($s) !== 32) {
            throw new RuntimeException('Invalid ECDSA signature component length.');
        }

        return $r.$s;
    }

    /**
     * @return array{0:int,1:int}
     */
    protected function readAsn1Length(string $der, int $offset): array
    {
        $first = ord($der[$offset++]);
        if (($first & 0x80) === 0) {
            return [$first, $offset];
        }

        $count  = $first & 0x7f;
        $length = 0;
        for ($i = 0; $i < $count; $i++) {
            $length = ($length << 8) | ord($der[$offset++]);
        }

        return [$length, $offset];
    }

    protected function publicKeyToPem(string $uncompressed): string
    {
        // SubjectPublicKeyInfo for P-256 uncompressed point
        $der = hex2bin(
            '3059301306072a8648ce3d020106082a8648ce3d030107034200'
        ).$uncompressed;

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    protected function ecPrivateKeyToPem(string $d, string $x, string $y): string
    {
        // RFC 5915 ECPrivateKey (fixed-size P-256) — more reliable than hand-rolled PKCS#8.
        $public = "\x04".$x.$y;
        $der    = "\x30\x77"
            ."\x02\x01\x01"
            ."\x04\x20".$d
            ."\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"
            ."\xa1\x44\x03\x42\x00".$public;

        return "-----BEGIN EC PRIVATE KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END EC PRIVATE KEY-----\n";
    }

    protected function hkdf(string $salt, string $ikm, string $info, int $length): string
    {
        $prk = $this->hkdfExtract($salt, $ikm);

        return $this->hkdfExpand($prk, $info, $length);
    }

    protected function hkdfExtract(string $salt, string $ikm): string
    {
        return hash_hmac('sha256', $ikm, $salt, true);
    }

    protected function hkdfExpand(string $prk, string $info, int $length): string
    {
        $t    = '';
        $last = '';
        for ($i = 1; strlen($t) < $length; $i++) {
            $last = hash_hmac('sha256', $last.$info.chr($i), $prk, true);
            $t .= $last;
        }

        return substr($t, 0, $length);
    }

    protected function endpointOrigin(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        if (! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('Invalid push endpoint URL.');
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url input.');
        }

        return $decoded;
    }
}
