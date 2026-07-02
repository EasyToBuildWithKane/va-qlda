<?php

namespace App\Services\AiAccount;

use App\Models\AiProposalScan;
use App\Models\SystemAccount;
use App\Support\Enums\AiProposalScanStatus;
use App\Support\Enums\ProposalSignatureRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Lưu file gốc, kết quả OCR và ảnh chữ ký của một lần quét Phiếu Đề Xuất.
 */
class ProposalScanRecorder
{
    /**
     * @param  array{raw_text: string, fields: array<string, mixed>, signatures: list<array<string, mixed>>, pages: int, duration_ms: int}  $result
     */
    public function record(UploadedFile $file, array $result, SystemAccount $creator): AiProposalScan
    {
        $scanId = (string) Str::uuid();
        $directory = "ai-proposals/scans/{$scanId}";

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $originalPath = $file->storeAs($directory, "original.{$extension}", 'public');

        return DB::transaction(function () use ($scanId, $directory, $originalPath, $file, $result, $creator) {
            $scan = AiProposalScan::create([
                'id' => $scanId,
                'original_path' => $originalPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size' => $file->getSize() ?: 0,
                'status' => AiProposalScanStatus::NeedsReview,
                'extracted_fields' => $this->normalizeFields($result['fields']),
                'raw_text' => $result['raw_text'] !== '' ? $result['raw_text'] : null,
                'pages' => $result['pages'],
                'duration_ms' => $result['duration_ms'],
                'created_by' => $creator->id,
            ]);

            foreach ($result['signatures'] as $index => $signature) {
                $role = ProposalSignatureRole::tryFrom((string) ($signature['role'] ?? ''))
                    ?? ProposalSignatureRole::Other;

                $imagePath = $this->storeSignatureImage(
                    $directory,
                    $index,
                    $role,
                    $signature['image_base64'] ?? null,
                );

                $scan->signatures()->create([
                    'role' => $role,
                    'signed' => (bool) ($signature['signed'] ?? false),
                    'signer_name' => $this->nullableString($signature['signer_name'] ?? null),
                    'confidence' => $this->clampConfidence($signature['confidence'] ?? 0),
                    'image_path' => $imagePath,
                    'bbox' => is_array($signature['bbox'] ?? null) ? $signature['bbox'] : null,
                    'page' => max(1, (int) ($signature['page'] ?? 1)),
                ]);
            }

            return $scan->load('signatures');
        });
    }

    public function markFailed(UploadedFile $file, string $error, SystemAccount $creator): AiProposalScan
    {
        $scanId = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $originalPath = $file->storeAs("ai-proposals/scans/{$scanId}", "original.{$extension}", 'public');

        return AiProposalScan::create([
            'id' => $scanId,
            'original_path' => $originalPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'status' => AiProposalScanStatus::Failed,
            'error_message' => $error,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * Giữ lại các trường {value, confidence} hợp lệ, loại giá trị rỗng.
     *
     * @return array<string, array{value: string, confidence: float}>
     */
    private function normalizeFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $key => $field) {
            if (! is_string($key) || ! is_array($field)) {
                continue;
            }
            $value = trim((string) ($field['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $normalized[$key] = [
                'value' => $value,
                'confidence' => $this->clampConfidence($field['confidence'] ?? 0),
            ];
        }

        return $normalized;
    }

    private function storeSignatureImage(string $directory, int $index, ProposalSignatureRole $role, mixed $base64): ?string
    {
        if (! is_string($base64) || $base64 === '') {
            return null;
        }

        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $path = "{$directory}/signatures/{$index}-{$role->value}.png";
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function clampConfidence(mixed $value): float
    {
        return round(min(max((float) $value, 0.0), 1.0), 3);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
