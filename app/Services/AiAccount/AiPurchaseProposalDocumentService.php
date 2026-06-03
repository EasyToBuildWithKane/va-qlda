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

        $docDate = Carbon::now()->locale('vi');
        $planned = $proposal->planned_use_date
            ? Carbon::parse($proposal->planned_use_date)->format('d/m/Y')
            : '';

        $purchaseType = $proposal->purchase_type instanceof AiPurchaseType
            ? $proposal->purchase_type
            : AiPurchaseType::tryFrom((string) $proposal->purchase_type) ?? AiPurchaseType::New;

        $objectives = $proposal->objectives ?? '';
        $content = $proposal->proposal_content ?? $proposal->justification ?? '';

        return [
            'doc_date' => $docDate->translatedFormat('l, ngày j \t\h\á\n\g n \n\ă\m Y'),
            'subject_about' => $proposal->subject_about ?? ('Đăng ký sử dụng '.$proposal->tool_name),
            'send_to_part1' => 'Ban Giám đốc',
            'send_to_part2' => $proposal->send_to ?? 'Phòng Công nghệ & Phòng Kế Toán',
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
