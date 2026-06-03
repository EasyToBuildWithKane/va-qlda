<?php

namespace App\Services\AiAccount;

use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
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

        return $this->templateVariablesFromInput($this->proposalToInput($proposal));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public function templateVariablesFromInput(array $input): array
    {
        $costAmount = max(0, (int) ($input['cost_amount'] ?? 0));
        $costUnit = AiAccountCostUnit::tryFrom((string) ($input['cost_unit'] ?? 'monthly'))
            ?? AiAccountCostUnit::Monthly;
        $monthly = $costAmount > 0
            ? app(AiAccountCostCalculator::class)->monthlyAmount($costAmount, $costUnit)
            : 0;

        $toolName = trim((string) ($input['tool_name'] ?? ''));
        $licenseType = trim((string) ($input['license_type'] ?? ''));
        $subjectAbout = trim((string) ($input['subject_about'] ?? ''));
        if ($subjectAbout === '' && $toolName !== '') {
            $subjectAbout = 'Đăng ký sử dụng '.$toolName;
        }

        $purchaseType = AiPurchaseType::tryFrom((string) ($input['purchase_type'] ?? 'new'))
            ?? AiPurchaseType::New;

        $content = trim((string) ($input['proposal_content'] ?? $input['justification'] ?? ''));
        $planned = ! empty($input['planned_use_date'])
            ? Carbon::parse($input['planned_use_date'])->format('d/m/Y')
            : '…';

        $proposerName = trim((string) ($input['proposer_name'] ?? ''));

        return [
            'doc_date' => $this->formatDocDateVi(Carbon::now()->timezone(config('app.timezone'))),
            'subject_about' => $subjectAbout !== '' ? $subjectAbout : '…',
            ...$this->splitSendTo($input['send_to'] ?? null),
            'proposer_name' => $proposerName !== '' ? $proposerName : '—',
            'proposer_position' => $this->fieldOrDash($input['proposer_position'] ?? null),
            'proposer_department' => $this->fieldOrDash($input['proposer_department'] ?? null),
            'proposal_content' => $content !== '' ? $content : '…',
            'objectives' => trim((string) ($input['objectives'] ?? '')),
            'tool_product_line' => trim($toolName.($licenseType !== '' ? ' - '.$licenseType : '')) ?: '—',
            'quantity' => (string) ($input['quantity'] ?? 1),
            'cost_monthly_formatted' => $monthly > 0 ? number_format($monthly, 0, ',', '.') : '…',
            'staff_count_line' => ((int) ($input['staff_count'] ?? 1)).' nhân sự',
            'recipient_name' => $this->fieldOrDash($input['recipient_name'] ?? $proposerName ?: null),
            'recipient_position' => $this->fieldOrDash($input['recipient_position'] ?? $input['proposer_position'] ?? null),
            'recipient_email' => $this->fieldOrDash($input['recipient_email'] ?? null),
            'recipient_phone' => $this->fieldOrDash($input['recipient_phone'] ?? null),
            'registration_email' => $this->fieldOrDash($input['registration_email'] ?? null),
            'planned_use_date' => $planned,
            'check_new' => $purchaseType === AiPurchaseType::New ? '☑' : '☐',
            'check_renewal' => $purchaseType === AiPurchaseType::Renewal ? '☑' : '☐',
        ];
    }

    /**
     * HTML xem trước trong modal — cùng partial với PDF.
     *
     * @param  array<string, mixed>  $input
     */
    public function renderPreviewHtml(array $input): string
    {
        return view('pdf.ai-purchase-proposal-preview', [
            'vars' => $this->templateVariablesFromInput($input),
            'checkboxImg' => asset('docx/checkbox.png'),
            'backgroundImg' => asset('docx/background.png'),
        ])->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalToInput(AiPurchaseProposal $proposal): array
    {
        return [
            'subject_about' => $proposal->subject_about,
            'send_to' => $proposal->send_to,
            'tool_name' => $proposal->tool_name,
            'license_type' => $proposal->license_type,
            'group_function' => $proposal->group_function instanceof AiAccountGroupFunction
                ? $proposal->group_function->value
                : $proposal->group_function,
            'cost_amount' => $proposal->cost_amount,
            'cost_unit' => $proposal->cost_unit instanceof AiAccountCostUnit
                ? $proposal->cost_unit->value
                : $proposal->cost_unit,
            'quantity' => $proposal->quantity,
            'proposer_name' => $proposal->proposer_name,
            'proposer_position' => $proposal->proposer_position,
            'proposer_department' => $proposal->proposer_department,
            'proposal_content' => $proposal->proposal_content,
            'justification' => $proposal->justification,
            'objectives' => $proposal->objectives,
            'staff_count' => $proposal->staff_count,
            'recipient_name' => $proposal->recipient_name,
            'recipient_position' => $proposal->recipient_position,
            'recipient_email' => $proposal->recipient_email,
            'recipient_phone' => $proposal->recipient_phone,
            'purchase_type' => $proposal->purchase_type instanceof AiPurchaseType
                ? $proposal->purchase_type->value
                : $proposal->purchase_type,
            'registration_email' => $proposal->registration_email,
            'planned_use_date' => $proposal->planned_use_date?->format('Y-m-d'),
        ];
    }

    private function fieldOrDash(?string $value): string
    {
        $v = trim((string) $value);

        return $v !== '' ? $v : '—';
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
