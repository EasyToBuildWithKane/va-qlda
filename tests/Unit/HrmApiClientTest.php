<?php

namespace Tests\Unit;

use App\Services\Hrm\HrmApiClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class HrmApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hrm.api.base_url' => 'https://hrm.test/api/v1',
            'hrm.api.token' => '1|test-token',
            'hrm.api.timeout' => 5,
        ]);
    }

    public function test_me_returns_data(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/me' => Http::response([
                'data' => ['client' => 'workspace'],
                'meta' => ['request_id' => 'r1'],
            ]),
        ]);

        $me = (new HrmApiClient)->me();

        $this->assertSame(['client' => 'workspace'], $me);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer 1|test-token'));
    }

    public function test_find_active_by_email_filters_status(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/employees*' => Http::response([
                'data' => [
                    [
                        'uuid' => 'u1',
                        'status' => 'terminated',
                        'company_email' => 'a@vaschools.edu.vn',
                    ],
                    [
                        'uuid' => 'u2',
                        'status' => 'active',
                        'company_email' => 'a@vaschools.edu.vn',
                    ],
                ],
            ]),
        ]);

        $row = (new HrmApiClient)->findActiveByEmail('a@vaschools.edu.vn');

        $this->assertSame('u2', $row['uuid'] ?? null);
    }

    public function test_find_by_uuid_returns_null_on_404(): void
    {
        Http::fake([
            'https://hrm.test/api/v1/employees/*' => Http::response([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy'],
            ], 404),
        ]);

        $this->assertNull((new HrmApiClient)->findByUuid('missing-uuid'));
    }

    public function test_throws_when_not_configured(): void
    {
        config(['hrm.api.token' => '']);

        $this->expectException(RuntimeException::class);
        (new HrmApiClient)->me();
    }
}
