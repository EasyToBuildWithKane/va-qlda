<?php

namespace App\Services\WeeklyReport\Export;

use App\Models\WeeklyReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class WeeklyReportPdfExporter
{
    public function __construct(private readonly WeeklyReportExportPresenter $presenter) {}

    public function download(WeeklyReport $report): Response
    {
        $data = $this->presenter->build($report);

        return Pdf::loadView('exports.weekly-report', ['data' => $data])
            ->setPaper('a4')
            ->download($this->filename($report).'.pdf');
    }

    private function filename(WeeklyReport $report): string
    {
        return 'BaoCaoTuan-'.$report->code();
    }
}
