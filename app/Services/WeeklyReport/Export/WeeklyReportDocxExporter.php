<?php

namespace App\Services\WeeklyReport\Export;

use App\Models\WeeklyReport;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WeeklyReportDocxExporter
{
    private const BRAND = '9A0036';

    public function __construct(private readonly WeeklyReportExportPresenter $presenter) {}

    public function download(WeeklyReport $report): StreamedResponse
    {
        $data = $this->presenter->build($report);

        $word = new PhpWord;
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(11);
        $word->addTitleStyle(1, ['bold' => true, 'color' => self::BRAND, 'size' => 16]);
        $word->addTitleStyle(2, ['bold' => true, 'color' => self::BRAND, 'size' => 12, 'allCaps' => true]);

        $section = $word->addSection();
        $section->addTitle('Báo cáo tuần', 1);
        $section->addText(
            $data['project'].' · '.$data['sprint'].' · '.$data['period'].' · '.$data['status_label'],
            ['color' => '64748B', 'size' => 9],
        );

        if ($data['executive_summary'] !== '') {
            $section->addTitle('Tóm tắt điều hành', 2);
            $section->addText($data['executive_summary']);
        }

        $section->addTitle('Chỉ số chính', 2);
        $table = $section->addTable(['borderSize' => 4, 'borderColor' => 'E2E8F0', 'cellMargin' => 60]);
        foreach (array_chunk($data['kpi'], 4) as $row) {
            $table->addRow();
            foreach ($row as $cell) {
                $tc = $table->addCell(2300);
                $tc->addText($cell['label'], ['size' => 8, 'color' => '64748B']);
                $tc->addText((string) $cell['value'], ['bold' => true, 'size' => 13]);
            }
        }

        if ($data['ai_summary'] !== '') {
            $section->addTitle('Nhận định', 2);
            $section->addText($data['ai_summary'], ['italic' => true]);
        }

        foreach ($data['cards'] as $card) {
            $section->addTitle($card['label'], 2);
            $this->bullets($section, $card['lines']);
        }

        $section->addTitle('Đánh giá rủi ro', 2);
        if ($data['risks'] !== []) {
            foreach ($data['risks'] as $r) {
                $section->addListItem('['.$r['level'].'] '.$r['label'].' — '.$r['reason'], 0);
            }
        } else {
            $section->addText('Không có rủi ro đáng kể trong tuần.', ['color' => '94A3B8']);
        }

        if ($data['feedback'] !== []) {
            $section->addTitle('Tổng hợp phản hồi', 2);
            foreach ($data['feedback'] as $b) {
                $section->addListItem($b['label'].': '.$b['count'], 0);
            }
        }

        if ($data['activity'] !== []) {
            $section->addTitle('Sự kiện nổi bật', 2);
            $this->bullets($section, $data['activity']);
        }

        return $this->stream($word, 'BaoCaoTuan-'.$report->code().'.docx');
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function bullets(\PhpOffice\PhpWord\Element\Section $section, array $lines): void
    {
        if ($lines === []) {
            $section->addText('Chưa có nội dung.', ['color' => '94A3B8']);

            return;
        }
        foreach ($lines as $line) {
            $section->addListItem($line, 0);
        }
    }

    private function stream(PhpWord $word, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($word) {
            IOFactory::createWriter($word, 'Word2007')->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
