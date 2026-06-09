<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Models\DailyReportScore;
use App\Models\Employee;

class DailyReportReviewTelegramFormatter
{
    private const SEP = '────────────────────';

    private const SECTION_FIELD_MAX = 700;

    private const MESSAGE_MAX = 4000;

    /**
     * @return array<int, array{jp: string, romaji: string, title: string, fields: array<int, array{label: string, key: string}>}>
     */
    private function horensoSections(): array
    {
        return [
            [
                'jp' => '報告',
                'romaji' => 'Hōkoku',
                'title' => 'Báo cáo',
                'fields' => [
                    ['label' => 'Mục tiêu hôm nay', 'key' => 'goals_today'],
                    ['label' => 'Tiến độ thực hiện', 'key' => 'progress_update'],
                ],
            ],
            [
                'jp' => '連絡',
                'romaji' => 'Renraku',
                'title' => 'Liên lạc',
                'fields' => [
                    ['label' => 'Khó khăn & vướng mắc', 'key' => 'blockers'],
                ],
            ],
            [
                'jp' => '相談',
                'romaji' => 'Sōdan',
                'title' => 'Trao đổi',
                'fields' => [
                    ['label' => 'Đề xuất cải tiến', 'key' => 'improvement_suggestions'],
                ],
            ],
            [
                'jp' => '計画',
                'romaji' => 'Keikaku',
                'title' => 'Kế hoạch',
                'fields' => [
                    ['label' => 'Kế hoạch ngày mai', 'key' => 'plan_tomorrow'],
                ],
            ],
        ];
    }

    public function format(DailyReport $report, DailyReportScore $score): string
    {
        $report->loadMissing('employee');
        $score->loadMissing('reviewer');

        $grade = $score->grade;
        $total = number_format((float) $score->total_score, 2, '.', '');
        $reviewerName = $score->reviewer?->full_name ?? '—';

        $lines = $this->beginMessage(
            $report,
            '📋 <b>HORENSO</b> · <b>報連相</b> · <i>Đã duyệt</i>',
        );

        $this->appendHorensoSections($lines, $report);

        $lines[] = self::SEP;
        $lines[] = '⭐ <b>Đánh giá</b> · '.$this->escape($grade->label()).' <code>('.$total.')</code>';
        $lines[] = '👨‍💼 <i>Duyệt bởi</i> '.$this->escape($reviewerName);

        if (filled($score->notes)) {
            $lines[] = '💬 <i>Nhận xét</i> '.$this->escape($this->truncate((string) $score->notes, 400));
        }

        return $this->finalizeMessage($lines, $report);
    }

    public function formatRejected(DailyReport $report, int $reviewerEmployeeId, string $notes): string
    {
        $report->loadMissing('employee');
        $reviewerName = Employee::query()->find($reviewerEmployeeId)?->full_name ?? '—';

        $lines = $this->beginMessage(
            $report,
            '↩️ <b>HORENSO</b> · <b>報連相</b> · <i>Trả lại chỉnh sửa</i>',
        );

        $this->appendHorensoSections($lines, $report);

        $lines[] = self::SEP;
        $lines[] = '❌ <b>Trả báo cáo</b> · <i>Vui lòng chỉnh sửa và nộp lại</i>';
        $lines[] = '👨‍💼 <i>Trả bởi</i> '.$this->escape($reviewerName);
        $lines[] = '📝 <i>Lý do</i> '.$this->escape($this->truncate($notes, 600));

        return $this->finalizeMessage($lines, $report);
    }

    /**
     * @return array<int, string>
     */
    private function beginMessage(DailyReport $report, string $statusLine): array
    {
        $employeeName = $report->employee?->full_name ?? '—';
        $employeeCode = $report->employee?->code;
        $dateLabel = $report->date->format('d/m/Y');

        $employeeLine = $employeeCode
            ? $this->escape($employeeName).' · <code>'.$this->escape($employeeCode).'</code>'
            : $this->escape($employeeName);

        $lines = [
            '━━━━━━━━━━━━━━━━━━━━',
            $statusLine,
            '━━━━━━━━━━━━━━━━━━━━',
            '',
            '👤 '.$employeeLine,
            '📅 '.$dateLabel.$this->projectSuffix($report),
        ];

        if (filled($report->title)) {
            $lines[] = '📌 '.$this->escape((string) $report->title);
        }

        if ($report->is_late) {
            $lines[] = '⚠️ <i>Nộp trễ</i>';
        }

        $lines[] = '';

        return $lines;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendHorensoSections(array &$lines, DailyReport $report): void
    {
        foreach ($this->horensoSections() as $section) {
            $lines[] = self::SEP;
            $lines[] = '<b>'.$section['jp'].'</b> '.$section['romaji'].' · '.$this->escape($section['title']);
            $lines[] = '';

            foreach ($section['fields'] as $field) {
                $plain = $this->htmlToPlain($report->{$field['key']} ?? null);
                $lines[] = '▸ <i>'.$this->escape($field['label']).'</i>';
                $lines[] = $this->formatFieldBody($plain);
                $lines[] = '';
            }
        }
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function finalizeMessage(array $lines, DailyReport $report): string
    {
        $url = route('daily-reports.show', $report);

        $lines[] = '';
        $lines[] = '🔗 <a href="'.$this->escapeAttr($url).'">Xem báo cáo trên VA-QLDA</a>';
        $lines[] = '━━━━━━━━━━━━━━━━━━━━';

        $text = implode("\n", $lines);

        if (mb_strlen($text) > self::MESSAGE_MAX) {
            $text = mb_substr($text, 0, self::MESSAGE_MAX - 20)."\n\n… <i>(đã rút gọn)</i>";
        }

        return $text;
    }

    private function projectSuffix(DailyReport $report): string
    {
        $projects = $report->projects;
        if (! is_array($projects) || $projects === []) {
            return '';
        }

        $labels = [];
        foreach ($projects as $item) {
            if (! is_array($item)) {
                continue;
            }
            $code = $item['code'] ?? null;
            $name = $item['name'] ?? null;
            if (is_string($code) && $code !== '') {
                $labels[] = $code;
            } elseif (is_string($name) && $name !== '') {
                $labels[] = $name;
            }
        }

        if ($labels === []) {
            return '';
        }

        return '  ·  🏷 '.$this->escape(implode(', ', array_unique($labels)));
    }

    private function formatFieldBody(?string $plain): string
    {
        if ($plain === null) {
            return '<i>—</i>';
        }

        return $this->escape($this->truncate($plain, self::SECTION_FIELD_MAX));
    }

    private function htmlToPlain(?string $html): ?string
    {
        if (! filled($html)) {
            return null;
        }

        $text = (string) $html;
        $text = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/li>\s*/i', "\n", $text) ?? $text;
        $text = preg_replace('/<li[^>]*>/i', '• ', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    private function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
