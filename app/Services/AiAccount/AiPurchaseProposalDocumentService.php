<?php

namespace App\Services\AiAccount;

use App\Models\AiPurchaseProposal;
use App\Support\Enums\AiAccountCostUnit;
use App\Support\Enums\AiAccountGroupFunction;
use App\Support\Enums\AiPurchaseType;
use App\Support\VndAmountInWords;
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
        $propCfg = config('ai_accounts.proposal', []);
        $payCfg = config('ai_accounts.payment_request', []);
        $department = trim((string) ($input['proposer_department'] ?? ''));
        if ($department === '') {
            $department = (string) ($propCfg['department'] ?? $payCfg['department'] ?? 'Phòng Công Nghệ');
        }

        return [
            'form_code' => (string) ($propCfg['form_code'] ?? ''),
            'school_name' => (string) ($propCfg['school_name'] ?? $payCfg['school_name'] ?? 'Hệ Thống Trường Việt Mỹ'),
            'department_header' => $department,
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
        $templatePath = $this->templatePath();
        if (! is_readable($templatePath)) {
            throw new \RuntimeException('Không tìm thấy file mẫu Word: '.$templatePath);
        }

        $processor = new TemplateProcessor($templatePath);
        $variables = $this->docxTemplateVariables($proposal);
        $known = array_flip($processor->getVariables());

        foreach ($variables as $key => $value) {
            if (! isset($known[$key])) {
                continue;
            }
            $processor->setValue($key, $this->wordValue($value));
        }

        $processor->saveAs($outputPath);
    }

    public function downloadDocx(AiPurchaseProposal $proposal): BinaryFileResponse
    {
        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmp = $dir.DIRECTORY_SEPARATOR.'ai-proposal-'.$proposal->id.'.docx';

        try {
            $this->fillDocx($proposal, $tmp);
        } catch (\Throwable $e) {
            report($e);
            abort(500, 'Không tạo được file Word. Vui lòng tải bản PDF hoặc liên hệ quản trị.');
        }

        if (! is_readable($tmp) || filesize($tmp) < 100) {
            abort(500, 'File Word không hợp lệ. Vui lòng tải bản PDF.');
        }

        $filename = 'Phieu_de_xuat_'.$this->safeFilename($proposal->tool_name).'.docx';

        return response()->download($tmp, $filename)->deleteFileAfterSend(true);
    }

    /**
     * @return array<string, string>
     */
    private function docxTemplateVariables(AiPurchaseProposal $proposal): array
    {
        $vars = $this->templateVariables($proposal);
        $vars['check_new'] = $vars['check_new'] === '☑' ? 'X' : ' ';
        $vars['check_renewal'] = $vars['check_renewal'] === '☑' ? 'X' : ' ';

        return $vars;
    }

    private function wordValue(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return str_replace(
            ['&', '<', '>'],
            ['&amp;', '&lt;', '&gt;'],
            $value,
        );
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
     * @return array<string, string>
     */
    public function paymentRequestVariables(AiPurchaseProposal $proposal): array
    {
        $proposal->loadMissing(['creator.employee']);
        $cfg = config('ai_accounts.payment_request', []);
        $tz = config('app.timezone');
        $docDate = Carbon::now()->timezone($tz);

        $toolName = trim($proposal->tool_name);
        $department = trim((string) ($proposal->proposer_department ?? ''));
        if ($department === '') {
            $department = (string) ($cfg['department'] ?? 'Phòng Công Nghệ');
        }

        $purchaseType = $proposal->purchase_type instanceof AiPurchaseType
            ? $proposal->purchase_type
            : AiPurchaseType::tryFrom((string) $proposal->purchase_type)
                ?? AiPurchaseType::New;

        $periodMonth = $proposal->planned_use_date
            ?? $proposal->start_date
            ?? $proposal->end_date
            ?? $proposal->created_at;
        $periodLabel = $periodMonth instanceof Carbon
            ? $periodMonth->format('m/Y')
            : ($periodMonth ? Carbon::parse($periodMonth)->format('m/Y') : $docDate->format('m/Y'));

        $paymentContent = match ($purchaseType) {
            AiPurchaseType::Renewal => sprintf(
                'Thanh toán chi phí gia hạn sử dụng công cụ AI %s cho %s tháng %s.',
                $toolName !== '' ? $toolName : '…',
                $department,
                $periodLabel,
            ),
            AiPurchaseType::New => sprintf(
                'Thanh toán chi phí mua mới sử dụng công cụ AI %s cho %s tháng %s.',
                $toolName !== '' ? $toolName : '…',
                $department,
                $periodLabel,
            ),
        };

        $amount = max(0, (int) $proposal->cost_amount);
        $paymentDate = $proposal->planned_use_date
            ?? $proposal->end_date
            ?? $proposal->start_date;
        $paymentDateFormatted = $paymentDate
            ? ($paymentDate instanceof Carbon ? $paymentDate : Carbon::parse($paymentDate))->format('d/m/Y')
            : '…';

        $proposerName = trim((string) $proposal->proposer_name);

        return [
            'form_code' => (string) ($cfg['form_code'] ?? 'KT.BM.03'),
            'company_unit' => (string) ($cfg['company_unit'] ?? 'Công ty CP Văn hóa Giáo dục Việt Mỹ'),
            'school_name' => (string) ($cfg['school_name'] ?? 'Hệ Thống Trường Việt Mỹ'),
            'department_header' => (string) ($cfg['department'] ?? 'Phòng Công Nghệ'),
            'doc_date' => $this->formatDocDateVi($docDate),
            'doc_day' => (string) $docDate->day,
            'doc_month' => (string) $docDate->month,
            'doc_year' => (string) $docDate->year,
            'send_to' => (string) ($cfg['send_to'] ?? 'Ban Tổng Giám Đốc'),
            'doc_number' => trim((string) ($proposal->proposal_code ?? '')) !== ''
                ? $proposal->proposal_code
                : '…………',
            'proposer_name' => $proposerName !== '' ? $proposerName : '—',
            'proposer_department' => $department,
            'payment_content' => $paymentContent,
            'amount_formatted' => $amount > 0 ? number_format($amount, 0, ',', '.').' VNĐ' : '…',
            'amount_in_words' => $amount > 0 ? VndAmountInWords::format($amount).'.' : '…',
            'payment_date' => $paymentDateFormatted,
            'payment_method' => (string) ($cfg['payment_method'] ?? 'Thanh toán bằng thẻ kế toán'),
        ];
    }

    public function downloadPaymentRequestPdf(AiPurchaseProposal $proposal): \Illuminate\Http\Response
    {
        $vars = $this->paymentRequestVariables($proposal);
        $pdf = Pdf::loadView('pdf.ai-payment-request', [
            'vars' => $vars,
        ])->setPaper('a4');

        $filename = 'Phieu_de_nghi_thanh_toan_'.$this->safeFilename($proposal->tool_name).'.pdf';

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

    private function safeFilename(string $name): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9_-]+/u', '_', $name) ?? 'de_xuat';

        return trim($slug, '_') ?: 'de_xuat';
    }
}
