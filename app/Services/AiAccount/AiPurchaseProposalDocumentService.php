<?php

namespace App\Services\AiAccount;

use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiPurchaseType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AiPurchaseProposalDocumentService
{
    public function templatePath(): string
    {
        return resource_path('templates/ai-purchase-proposal.docx');
    }

    /**
     * @return array<string, string>
     */
    public function templateVariables(AiPurchaseProposal $proposal): array
    {
        $proposal->loadMissing(['creator.employee']);

        $monthly = app(AiAccountCostCalculator::class)
            ->monthlyAmount($proposal->cost_amount, $proposal->cost_unit);

        $docDate = Carbon::now()->timezone(config('app.timezone'));
        $planned = $proposal->planned_use_date
            ? Carbon::parse($proposal->planned_use_date)->format('d/m/Y')
            : '';

        $purchaseType = $proposal->purchase_type instanceof AiPurchaseType
            ? $proposal->purchase_type
            : AiPurchaseType::tryFrom((string) $proposal->purchase_type) ?? AiPurchaseType::New;

        $objectives = $proposal->objectives ?? '';
        $content = $proposal->proposal_content ?? $proposal->justification ?? '';

        return [
            'doc_date' => $this->formatDocDateVi($docDate),
            'subject_about' => $proposal->subject_about ?? ('Đăng ký sử dụng '.$proposal->tool_name),
            ...$this->splitSendTo($proposal->send_to),
            'proposer_name' => $proposal->proposer_name ?? '—',
            'proposer_position' => $proposal->proposer_position ?? '—',
            'proposer_department' => $proposal->proposer_department ?? '—',
            'proposal_content' => $content,
            'objectives' => $objectives,
            'tool_product_line' => $proposal->tool_name.' - '.$proposal->license_type,
            'quantity' => (string) ($proposal->quantity ?? 1),
            'cost_monthly_formatted' => number_format($monthly, 0, ',', '.'),
            'staff_count_line' => ($proposal->staff_count ?? 1).' nhân sự',
            'recipient_name' => $proposal->recipient_name ?? $proposal->proposer_name ?? '—',
            'recipient_position' => $proposal->recipient_position ?? $proposal->proposer_position ?? '—',
            'recipient_email' => $proposal->recipient_email ?? '—',
            'recipient_phone' => $proposal->recipient_phone ?? '—',
            'registration_email' => $proposal->registration_email ?? '—',
            'planned_use_date' => $planned,
            'check_new' => $purchaseType === AiPurchaseType::New ? '☑' : '☐',
            'check_renewal' => $purchaseType === AiPurchaseType::Renewal ? '☑' : '☐',
        ];
    }

    public function fillDocx(AiPurchaseProposal $proposal, string $outputPath): void
    {
        $processor = new TemplateProcessor($this->templatePath());
        foreach ($this->templateVariables($proposal) as $key => $value) {
            $processor->setValue($key, $this->escapeWordValue($value));
        }
        $processor->saveAs($outputPath);
    }

    public function downloadDocx(AiPurchaseProposal $proposal): BinaryFileResponse
    {
        $tmp = storage_path('app/temp/ai-proposal-'.$proposal->id.'.docx');
        if (! is_dir(dirname($tmp))) {
            mkdir(dirname($tmp), 0755, true);
        }
        $this->fillDocx($proposal, $tmp);
        $filename = 'Phieu_de_xuat_'.$this->safeFilename($proposal->tool_name).'.docx';

        return response()->download($tmp, $filename)->deleteFileAfterSend(true);
    }

    public function downloadPdf(AiPurchaseProposal $proposal): \Illuminate\Http\Response
    {
        $vars = $this->templateVariables($proposal);
        $pdf = Pdf::loadView('pdf.ai-purchase-proposal', [
            'proposal' => $proposal,
            'vars' => $vars,
        ])->setPaper('a4');

        $filename = 'Phieu_de_xuat_'.$this->safeFilename($proposal->tool_name).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array{send_to_part1: string, send_to_part2: string}
     */
    private function splitSendTo(?string $sendTo): array
    {
        $default = config('ai_accounts.proposal.send_to_default', "Ban Giám đốc\nPhòng Công nghệ & Phòng Kế Toán");
        $raw = trim($sendTo ?? '') !== '' ? $sendTo : $default;
        $lines = array_values(array_filter(array_map(
            trim(...),
            preg_split('/\r\n|\r|\n/', (string) $raw) ?: [],
        )));

        return [
            'send_to_part1' => $lines[0] ?? 'Ban Giám đốc',
            'send_to_part2' => count($lines) > 1
                ? implode("\n", array_slice($lines, 1))
                : 'Phòng Công nghệ & Phòng Kế Toán',
        ];
    }

    /** Ngày tháng tiếng Việt chuẩn PDX — tránh translatedFormat lỗi font DomPDF. */
    private function formatDocDateVi(Carbon $date): string
    {
        $weekdays = [
            0 => 'Chủ Nhật',
            1 => 'Thứ Hai',
            2 => 'Thứ Ba',
            3 => 'Thứ Tư',
            4 => 'Thứ Năm',
            5 => 'Thứ Sáu',
            6 => 'Thứ Bảy',
        ];

        return sprintf(
            '%s, ngày %d tháng %d năm %d',
            $weekdays[$date->dayOfWeek] ?? 'Thứ Hai',
            $date->day,
            $date->month,
            $date->year,
        );
    }

    private function escapeWordValue(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function safeFilename(string $name): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/u', '_', $name) ?? 'de_xuat';

        return trim($slug, '_') ?: 'de_xuat';
    }
}
