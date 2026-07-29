<?php

namespace App\Services\Hrm;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * HTTP client M2M tới HRM Public API v1 (Bearer Sanctum).
 * JWT SSO user ≠ token này — không dùng lẫn.
 */
final class HrmApiClient
{
    public function isConfigured(): bool
    {
        return filled(config('hrm.api.base_url'))
            && filled(config('hrm.api.token'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function me(): ?array
    {
        return $this->getData('/me');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findEmployeesByEmail(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return [];
        }

        $data = $this->getData('/employees', ['email' => $email]);

        if (! is_array($data)) {
            return [];
        }

        // list endpoint: data là mảng; một số lỗi có thể trả object.
        if ($data !== [] && array_is_list($data)) {
            /** @var list<array<string, mixed>> $data */
            return $data;
        }

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByEmail(string $email): ?array
    {
        foreach ($this->findEmployeesByEmail($email) as $row) {
            if (($row['status'] ?? null) === 'active') {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByLegacyUserId(int $legacyUserId): ?array
    {
        if ($legacyUserId < 1) {
            return null;
        }

        return $this->getData('/employees/by-legacy-user/'.$legacyUserId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUuid(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            return null;
        }

        return $this->getData('/employees/'.rawurlencode($uuid));
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    private function getData(string $path, array $query = []): mixed
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Chưa cấu hình HRM_API_BASE_URL / HRM_API_TOKEN.');
        }

        try {
            $response = $this->http()->get(ltrim($path, '/'), $query);
        } catch (\Throwable $e) {
            Log::warning('hrm.api.request_failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            Log::warning('hrm.api.http_error', [
                'path' => $path,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            $response->throw();
        }

        $data = $response->json('data');

        return is_array($data) ? $data : null;
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl((string) config('hrm.api.base_url'))
            ->withToken((string) config('hrm.api.token'))
            ->acceptJson()
            ->timeout(max(3, (int) config('hrm.api.timeout', 10)))
            ->retry(0, 0);

        $verify = $this->sslVerifyOption();
        if ($verify !== true) {
            $request = $request->withOptions(['verify' => $verify]);
        }

        return $request;
    }

    /**
     * Guzzle/cURL trên Windows thường bỏ qua openssl.cafile nếu curl.cainfo trống
     * → error 60. Truyền rõ đường CA bundle, hoặc tắt verify khi local cấu hình.
     */
    private function sslVerifyOption(): bool|string
    {
        if (! config('hrm.api.verify_ssl', true)) {
            return false;
        }

        $configured = trim((string) config('hrm.api.ca_bundle', ''));
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach ([ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $path) {
            $path = trim((string) $path);
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return true;
    }
}
