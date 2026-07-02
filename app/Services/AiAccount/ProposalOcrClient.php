<?php

namespace App\Services\AiAccount;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client cho Python OCR service (ocr-service/) — trích xuất
 * trường dữ liệu + vùng chữ ký từ file Phiếu Đề Xuất.
 */
class ProposalOcrClient
{
    public function isConfigured(): bool
    {
        return (bool) config('services.proposal_ocr.url');
    }

    /**
     * @return array{raw_text: string, fields: array<string, array{value: ?string, confidence: float}>, signatures: list<array<string, mixed>>, pages: int, duration_ms: int}
     */
    public function extract(UploadedFile $file): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Dịch vụ OCR chưa được cấu hình. Vui lòng liên hệ quản trị viên.');
        }

        $url = rtrim((string) config('services.proposal_ocr.url'), '/').'/v1/extract';

        try {
            $response = Http::timeout((int) config('services.proposal_ocr.timeout', 30))
                ->withHeaders(['X-OCR-Token' => (string) config('services.proposal_ocr.token')])
                ->attach('file', $file->get(), $file->getClientOriginalName(), [
                    'Content-Type' => $file->getMimeType(),
                ])
                ->post($url);
        } catch (ConnectionException) {
            throw new RuntimeException('Không kết nối được dịch vụ OCR. Vui lòng thử lại sau.');
        }

        if ($response->status() === 422) {
            throw new RuntimeException('File không đọc được. Vui lòng kiểm tra định dạng PDF/JPG/PNG và thử lại.');
        }

        if ($response->failed()) {
            throw new RuntimeException('Dịch vụ OCR gặp lỗi khi xử lý tài liệu. Vui lòng thử lại sau.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Dịch vụ OCR trả về dữ liệu không hợp lệ.');
        }

        return [
            'raw_text' => (string) ($data['raw_text'] ?? ''),
            'fields' => is_array($data['fields'] ?? null) ? $data['fields'] : [],
            'signatures' => is_array($data['signatures'] ?? null) ? array_values($data['signatures']) : [],
            'pages' => max(1, (int) ($data['pages'] ?? 1)),
            'duration_ms' => (int) ($data['duration_ms'] ?? 0),
        ];
    }
}
