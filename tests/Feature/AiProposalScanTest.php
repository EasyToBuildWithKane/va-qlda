<?php

namespace Tests\Feature;

use App\Models\AiProposalScan;
use App\Models\AiPurchaseProposal;
use App\Models\SystemAccount;
use App\Support\Enums\AiProposalScanStatus;
use App\Support\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiProposalScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config([
            'services.proposal_ocr.url' => 'http://ocr.test',
            'services.proposal_ocr.token' => 'test-token',
        ]);
    }

    private function actingAsUser(): SystemAccount
    {
        $account = SystemAccount::factory()->create();
        $this->actingAs($account, 'system');

        return $account;
    }

    /** @return array<string, mixed> */
    private function ocrResponsePayload(): array
    {
        return [
            'raw_text' => "PHIẾU ĐỀ XUẤT\nSố: 15/PĐX-2026",
            'fields' => [
                'proposal_code' => ['value' => '15/PĐX-2026', 'confidence' => 0.95],
                'proposal_date' => ['value' => '2026-06-30', 'confidence' => 0.9],
                'proposer_name' => ['value' => 'Nguyễn Văn A', 'confidence' => 0.92],
                'proposer_department' => ['value' => 'Phòng Công nghệ', 'confidence' => 0.88],
                'subject_about' => ['value' => 'Mua sắm thiết bị trình chiếu', 'confidence' => 0.9],
                'proposal_content' => ['value' => 'Đề xuất trang bị 02 máy chiếu cho phòng họp tầng 3 phục vụ đào tạo.', 'confidence' => 0.85],
                'cost_amount' => ['value' => '15000000', 'confidence' => 0.6],
            ],
            'signatures' => [
                [
                    'role' => 'proposer',
                    'signed' => true,
                    'signer_name' => 'Nguyễn Văn A',
                    'confidence' => 0.9,
                    'bbox' => [0.1, 0.8, 0.3, 0.9],
                    'page' => 1,
                    'image_base64' => base64_encode('fake-signature-png'),
                ],
                [
                    'role' => 'board_of_directors',
                    'signed' => false,
                    'signer_name' => null,
                    'confidence' => 0.75,
                    'bbox' => [0.6, 0.8, 0.9, 0.9],
                    'page' => 1,
                    'image_base64' => null,
                ],
            ],
            'pages' => 1,
            'duration_ms' => 3200,
        ];
    }

    private function uploadScan(): AiProposalScan
    {
        Http::fake(['http://ocr.test/*' => Http::response($this->ocrResponsePayload())]);

        $this->postJson(route('api.ai-accounts.proposal-scans.store'), [
            'file' => UploadedFile::fake()->image('phieu-de-xuat.jpg', 1200, 1600),
        ])->assertCreated();

        return AiProposalScan::query()->firstOrFail();
    }

    public function test_upload_requires_auth(): void
    {
        $this->postJson(route('api.ai-accounts.proposal-scans.store'), [
            'file' => UploadedFile::fake()->image('phieu.jpg'),
        ])->assertUnauthorized();
    }

    public function test_upload_rejects_unsupported_file_type(): void
    {
        $this->actingAsUser();

        $this->postJson(route('api.ai-accounts.proposal-scans.store'), [
            'file' => UploadedFile::fake()->create('phieu.txt', 10, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    public function test_upload_extracts_fields_and_signatures(): void
    {
        $user = $this->actingAsUser();
        Http::fake(['http://ocr.test/*' => Http::response($this->ocrResponsePayload())]);

        $response = $this->postJson(route('api.ai-accounts.proposal-scans.store'), [
            'file' => UploadedFile::fake()->image('phieu-de-xuat.jpg', 1200, 1600),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scan.status.value', AiProposalScanStatus::NeedsReview->value)
            ->assertJsonPath('data.scan.fields.proposer_name.value', 'Nguyễn Văn A')
            ->assertJsonPath('data.scan.signatures.0.role.value', 'proposer')
            ->assertJsonPath('data.scan.signatures.0.signed', true)
            ->assertJsonPath('data.scan.signatures.1.role.value', 'board_of_directors')
            ->assertJsonPath('data.scan.signatures.1.signed', false);

        $scan = AiProposalScan::query()->firstOrFail();
        $this->assertSame($user->id, $scan->created_by);
        $this->assertTrue(Storage::disk('public')->exists($scan->original_path));

        $signature = $scan->signatures()->where('role', 'proposer')->firstOrFail();
        $this->assertNotNull($signature->image_path);
        $this->assertTrue(Storage::disk('public')->exists($signature->image_path));
        $this->assertNull($scan->signatures()->where('role', 'board_of_directors')->value('image_path'));
    }

    public function test_upload_records_failed_scan_when_ocr_service_errors(): void
    {
        $this->actingAsUser();
        Http::fake(['http://ocr.test/*' => Http::response(['detail' => 'boom'], 500)]);

        $this->postJson(route('api.ai-accounts.proposal-scans.store'), [
            'file' => UploadedFile::fake()->image('phieu.jpg'),
        ])->assertStatus(422)->assertJsonPath('success', false);

        $scan = AiProposalScan::query()->firstOrFail();
        $this->assertSame(AiProposalScanStatus::Failed, $scan->status);
        $this->assertNotNull($scan->error_message);
    }

    public function test_creator_can_update_extracted_fields(): void
    {
        $this->actingAsUser();
        $scan = $this->uploadScan();

        $this->patchJson(route('api.ai-accounts.proposal-scans.update', ['scan' => $scan->id]), [
            'fields' => [
                'proposer_name' => ['value' => 'Trần Thị B'],
                'cost_amount' => ['value' => '20000000'],
                'subject_about' => ['value' => 'Mua sắm thiết bị trình chiếu'],
                'proposal_content' => ['value' => 'Đề xuất trang bị 02 máy chiếu cho phòng họp tầng 3 phục vụ đào tạo.'],
            ],
        ])->assertOk()->assertJsonPath('data.scan.fields.proposer_name.value', 'Trần Thị B');

        $fields = $scan->fresh()->extracted_fields;
        $this->assertSame('Trần Thị B', $fields['proposer_name']['value']);
        $this->assertSame(1.0, (float) $fields['proposer_name']['confidence']);
        $this->assertArrayNotHasKey('proposal_code', $fields);
    }

    public function test_update_rejects_unknown_field_keys(): void
    {
        $this->actingAsUser();
        $scan = $this->uploadScan();

        $this->patchJson(route('api.ai-accounts.proposal-scans.update', ['scan' => $scan->id]), [
            'fields' => ['hacked_key' => ['value' => 'x']],
        ])->assertUnprocessable();
    }

    public function test_other_member_cannot_view_or_update_scan(): void
    {
        $this->actingAsUser();
        $scan = $this->uploadScan();

        $other = SystemAccount::factory()->create();
        $this->actingAs($other, 'system');

        $this->getJson(route('api.ai-accounts.proposal-scans.show', ['scan' => $scan->id]))
            ->assertForbidden();
        $this->patchJson(route('api.ai-accounts.proposal-scans.update', ['scan' => $scan->id]), [
            'fields' => ['proposer_name' => ['value' => 'Kẻ lạ']],
        ])->assertForbidden();
    }

    public function test_admin_can_view_any_scan(): void
    {
        $this->actingAsUser();
        $scan = $this->uploadScan();

        $admin = SystemAccount::factory()->role(SystemRole::Admin)->create();
        $this->actingAs($admin, 'system');

        $this->getJson(route('api.ai-accounts.proposal-scans.show', ['scan' => $scan->id]))
            ->assertOk()
            ->assertJsonPath('data.scan.id', $scan->id);
    }

    public function test_confirm_creates_pending_proposal_with_attachment(): void
    {
        $this->actingAsUser();
        $scan = $this->uploadScan();

        $response = $this->postJson(route('api.ai-accounts.proposal-scans.confirm', ['scan' => $scan->id]), [
            'subject_about' => 'Mua sắm thiết bị trình chiếu',
            'proposer_name' => 'Nguyễn Văn A',
            'proposer_department' => 'Phòng Công nghệ',
            'proposal_content' => 'Đề xuất trang bị 02 máy chiếu cho phòng họp tầng 3 phục vụ đào tạo.',
            'cost_amount' => 15_000_000,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scan.status.value', AiProposalScanStatus::Confirmed->value);

        $proposal = AiPurchaseProposal::query()->firstOrFail();
        $this->assertSame('Nguyễn Văn A', $proposal->proposer_name);
        $this->assertSame(15_000_000, $proposal->cost_amount);
        $this->assertSame([$scan->original_path], $proposal->attachment_paths);
        $this->assertStringContainsString('15/PĐX-2026', $proposal->description);

        $this->assertSame($proposal->id, $scan->fresh()->ai_purchase_proposal_id);
    }

    public function test_confirm_requires_cost_amount(): void
    {
        $this->actingAsUser();
        $scan = $this->uploadScan();

        $this->postJson(route('api.ai-accounts.proposal-scans.confirm', ['scan' => $scan->id]), [
            'subject_about' => 'Mua sắm thiết bị trình chiếu',
            'proposer_name' => 'Nguyễn Văn A',
            'proposal_content' => 'Đề xuất trang bị 02 máy chiếu cho phòng họp tầng 3 phục vụ đào tạo.',
        ])->assertUnprocessable()->assertJsonValidationErrors('cost_amount');
    }

    public function test_confirmed_scan_cannot_be_confirmed_again(): void
    {
        $this->actingAsUser();
        $scan = $this->uploadScan();

        $payload = [
            'subject_about' => 'Mua sắm thiết bị trình chiếu',
            'proposer_name' => 'Nguyễn Văn A',
            'proposal_content' => 'Đề xuất trang bị 02 máy chiếu cho phòng họp tầng 3 phục vụ đào tạo.',
            'cost_amount' => 15_000_000,
        ];

        $this->postJson(route('api.ai-accounts.proposal-scans.confirm', ['scan' => $scan->id]), $payload)->assertOk();
        $this->postJson(route('api.ai-accounts.proposal-scans.confirm', ['scan' => $scan->id]), $payload)->assertForbidden();
    }

    public function test_scan_file_and_signature_file_are_served(): void
    {
        $this->actingAsUser();
        $scan = $this->uploadScan();

        $this->get(route('api.ai-accounts.proposal-scans.file', ['scan' => $scan->id]))->assertOk();

        $signature = $scan->signatures()->whereNotNull('image_path')->firstOrFail();
        $this->get(route('api.ai-accounts.proposal-scans.signatures.file', [
            'scan' => $scan->id,
            'signature' => $signature->id,
        ]))->assertOk();
    }

    public function test_upload_fails_gracefully_when_service_unconfigured(): void
    {
        config(['services.proposal_ocr.url' => null]);
        $this->actingAsUser();

        $this->postJson(route('api.ai-accounts.proposal-scans.store'), [
            'file' => UploadedFile::fake()->image('phieu.jpg'),
        ])->assertStatus(422)->assertJsonPath('success', false);
    }
}
