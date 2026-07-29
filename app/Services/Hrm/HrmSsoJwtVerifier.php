<?php

namespace App\Services\Hrm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verify JWT RS256 do HRM phát cho SSO user (ADR-013 phía va-hrm).
 *
 * Verify offline qua JWKS ({BASE}/.well-known/jwks.json, cache ~1h, khớp
 * `kid` header — miss thì refetch một lần để đỡ xoay khóa). Bắt buộc khớp
 * `aud` (= client workspace), `iss` (= SSO_ISSUER phía HRM) và `exp`.
 *
 * JWT SSO user ≠ HRM_API_TOKEN (Sanctum M2M) — không dùng lẫn.
 */
final class HrmSsoJwtVerifier
{
    private const JWKS_CACHE_KEY = 'hrm_sso.jwks';

    /**
     * @return array<string, mixed> claims đã verify
     *
     * @throws RuntimeException khi token không hợp lệ
     */
    public function verify(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('JWT sai định dạng.');
        }

        [$headerB64, $claimsB64, $signatureB64] = $parts;

        $header = json_decode($this->base64UrlDecode($headerB64), true);
        if (! is_array($header) || ($header['alg'] ?? null) !== 'RS256') {
            throw new RuntimeException('JWT header không hợp lệ (chỉ hỗ trợ RS256).');
        }

        $publicKeyPem = $this->publicKeyForKid(is_string($header['kid'] ?? null) ? $header['kid'] : null);

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            throw new RuntimeException('Public key JWKS của HRM không hợp lệ.');
        }

        $valid = openssl_verify(
            $headerB64.'.'.$claimsB64,
            $this->base64UrlDecode($signatureB64),
            $publicKey,
            OPENSSL_ALGO_SHA256,
        );

        if ($valid !== 1) {
            throw new RuntimeException('Chữ ký JWT không hợp lệ.');
        }

        $claims = json_decode($this->base64UrlDecode($claimsB64), true);
        if (! is_array($claims)) {
            throw new RuntimeException('JWT payload không hợp lệ.');
        }

        if (! isset($claims['exp']) || time() >= (int) $claims['exp']) {
            throw new RuntimeException('JWT đã hết hạn.');
        }

        $issuer = (string) config('services.hrm_sso.issuer');
        if ($issuer === '' || ($claims['iss'] ?? null) !== $issuer) {
            throw new RuntimeException('JWT issuer không khớp.');
        }

        $audience = (string) config('services.hrm_sso.audience');
        if ($audience === '' || ($claims['aud'] ?? null) !== $audience) {
            throw new RuntimeException('JWT audience không khớp.');
        }

        return $claims;
    }

    /**
     * Tìm public key PEM theo `kid`; kid lạ → refetch JWKS (bypass cache) một lần.
     */
    private function publicKeyForKid(?string $kid): string
    {
        $jwk = $this->matchJwk($this->fetchJwks(false), $kid)
            ?? $this->matchJwk($this->fetchJwks(true), $kid);

        if ($jwk === null) {
            throw new RuntimeException('Không tìm thấy khóa JWKS khớp kid của JWT.');
        }

        return $this->jwkToPem($jwk);
    }

    /**
     * @param  list<array<string, string>>  $keys
     * @return array<string, string>|null
     */
    private function matchJwk(array $keys, ?string $kid): ?array
    {
        foreach ($keys as $key) {
            if (($key['kty'] ?? null) !== 'RSA' || ! isset($key['n'], $key['e'])) {
                continue;
            }

            if ($kid === null || ($key['kid'] ?? null) === $kid) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, string>>
     */
    private function fetchJwks(bool $fresh): array
    {
        if ($fresh) {
            Cache::forget(self::JWKS_CACHE_KEY);
        }

        $ttl = max(60, (int) config('services.hrm_sso.jwks_cache_ttl', 3600));

        return Cache::remember(self::JWKS_CACHE_KEY, $ttl, function (): array {
            $response = Http::acceptJson()->timeout(10)->get($this->jwksUrl());

            if (! $response->successful()) {
                throw new RuntimeException('Không tải được JWKS từ HRM (HTTP '.$response->status().').');
            }

            $keys = $response->json('keys');
            if (! is_array($keys) || $keys === []) {
                throw new RuntimeException('JWKS của HRM rỗng hoặc sai định dạng.');
            }

            return array_values($keys);
        });
    }

    private function jwksUrl(): string
    {
        $configured = (string) config('services.hrm_sso.jwks_url', '');
        if ($configured !== '') {
            return $configured;
        }

        $base = (string) config('services.hrm_sso.base_url');
        if ($base === '') {
            throw new RuntimeException('Chưa cấu hình HRM_SSO_BASE_URL.');
        }

        return $base.'/.well-known/jwks.json';
    }

    /**
     * Dựng PEM SubjectPublicKeyInfo từ JWK RSA (n, e) — không thêm dependency.
     *
     * @param  array<string, string>  $jwk
     */
    private function jwkToPem(array $jwk): string
    {
        $modulus = $this->base64UrlDecode($jwk['n']);
        $exponent = $this->base64UrlDecode($jwk['e']);

        if ($modulus === '' || $exponent === '') {
            throw new RuntimeException('JWK RSA thiếu modulus/exponent.');
        }

        $rsaPublicKey = $this->derSequence(
            $this->derInteger($modulus).$this->derInteger($exponent),
        );

        // AlgorithmIdentifier rsaEncryption (OID 1.2.840.113549.1.1.1) + NULL params.
        $algorithm = hex2bin('300d06092a864886f70d0101010500');
        $subjectPublicKeyInfo = $this->derSequence(
            $algorithm."\x03".$this->derLength(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey,
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            .'-----END PUBLIC KEY-----';
    }

    private function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->derLength(strlen($bytes)).$bytes;
    }

    private function derSequence(string $contents): string
    {
        return "\x30".$this->derLength(strlen($contents)).$contents;
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
