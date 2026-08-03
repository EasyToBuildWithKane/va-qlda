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
     * Danh sách org-units (cursor-paginate, gom đủ trang).
     * Query gợi ý: type=department|unit|branch|headquarter, company=<uuid>, per_page≤100.
     *
     * @param  array<string, scalar|null>  $query
     * @return list<array<string, mixed>>
     */
    public function listOrgUnits(array $query = []): array
    {
        $all = [];
        $cursor = null;
        $perPage = (int) ($query['per_page'] ?? 100);
        $perPage = max(1, min(100, $perPage));

        for ($page = 0; $page < 50; $page++) {
            $params = array_merge($query, ['per_page' => $perPage]);
            if (is_string($cursor) && $cursor !== '') {
                $params['cursor'] = $cursor;
            } else {
                unset($params['cursor']);
            }

            $envelope = $this->getEnvelope('/org-units', $params);
            $data = $envelope['data'] ?? null;
            if (! is_array($data) || ! array_is_list($data)) {
                break;
            }

            foreach ($data as $row) {
                if (is_array($row)) {
                    /** @var array<string, mixed> $row */
                    $all[] = $row;
                }
            }

            $next = $envelope['meta']['cursor']['next'] ?? null;
            if (! is_string($next) || $next === '') {
                break;
            }
            $cursor = $next;
        }

        return $all;
    }

    /**
     * Danh mục chức danh / job titles (nếu HRM hỗ trợ).
     * Thử lần lượt các path phổ biến; 404/403 → mảng rỗng (không throw).
     *
     * @param  array<string, scalar|null>  $query
     * @return list<array<string, mixed>>
     */
    public function listJobTitles(array $query = []): array
    {
        return $this->listCatalogSafe([
            '/job-titles',
            '/titles',
            '/positions',
            '/job-positions',
        ], $query);
    }

    /**
     * Danh mục cấp bậc / rank / level (nếu HRM hỗ trợ).
     *
     * @param  array<string, scalar|null>  $query
     * @return list<array<string, mixed>>
     */
    public function listRanks(array $query = []): array
    {
        return $this->listCatalogSafe([
            '/ranks',
            '/job-levels',
            '/levels',
            '/grades',
            '/employee-levels',
        ], $query);
    }

    /**
     * @param  list<string>  $paths
     * @param  array<string, scalar|null>  $query
     * @return list<array<string, mixed>>
     */
    private function listCatalogSafe(array $paths, array $query = []): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        foreach ($paths as $path) {
            try {
                $rows = $this->listOrgUnitsStyle($path, $query);
                if ($rows !== []) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                Log::info('hrm.api.catalog_skip', [
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return [];
    }

    /**
     * Cursor-paginate giống listOrgUnits nhưng bỏ qua HTTP lỗi (trả []).
     *
     * @param  array<string, scalar|null>  $query
     * @return list<array<string, mixed>>
     */
    private function listOrgUnitsStyle(string $path, array $query = []): array
    {
        $all = [];
        $cursor = null;
        $perPage = (int) ($query['per_page'] ?? 100);
        $perPage = max(1, min(100, $perPage));

        for ($page = 0; $page < 50; $page++) {
            $params = array_merge($query, ['per_page' => $perPage]);
            if (is_string($cursor) && $cursor !== '') {
                $params['cursor'] = $cursor;
            } else {
                unset($params['cursor']);
            }

            $envelope = $this->getEnvelopeAllowFail($path, $params);
            if ($envelope === null) {
                return [];
            }

            $data = $envelope['data'] ?? null;
            if (! is_array($data)) {
                break;
            }
            if (! array_is_list($data)) {
                // Single object catalog → wrap
                $data = [$data];
            }

            foreach ($data as $row) {
                if (is_array($row)) {
                    $all[] = $row;
                }
            }

            $next = $envelope['meta']['cursor']['next'] ?? null;
            if (! is_string($next) || $next === '') {
                break;
            }
            $cursor = $next;
        }

        return $all;
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array{data: mixed, meta: array<string, mixed>}|null
     */
    private function getEnvelopeAllowFail(string $path, array $query = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->http()->get(ltrim($path, '/'), $query);
        } catch (\Throwable $e) {
            Log::info('hrm.api.catalog_request_failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->status() === 404 || $response->status() === 403 || $response->status() === 401) {
            return null;
        }

        if ($response->failed()) {
            Log::info('hrm.api.catalog_http_error', [
                'path' => $path,
                'status' => $response->status(),
            ]);

            return null;
        }

        $meta = $response->json('meta');

        return [
            'data' => $response->json('data'),
            'meta' => is_array($meta) ? $meta : [],
        ];
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    private function getData(string $path, array $query = []): mixed
    {
        $envelope = $this->getEnvelope($path, $query);
        $data = $envelope['data'] ?? null;

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array{data: mixed, meta: array<string, mixed>}
     */
    private function getEnvelope(string $path, array $query = []): array
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
            return ['data' => null, 'meta' => []];
        }

        if ($response->failed()) {
            Log::warning('hrm.api.http_error', [
                'path' => $path,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            $response->throw();
        }

        $meta = $response->json('meta');

        return [
            'data' => $response->json('data'),
            'meta' => is_array($meta) ? $meta : [],
        ];
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
