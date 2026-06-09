<?php

namespace App\Application\DailyReport;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Models\DailyReportScore;
use App\Models\Employee;

class DailyReportReviewTelegramFormatter
{
    private const FIELD_MAX = 520;

    private const MESSAGE_MAX = 4000;

    /**
     * @return array<int, array{jp: string, fields: array<int, array{short: string, key: string, optional?: bool}>}>
     */
    private function horensoBlocks(): array
    {
        return [
            [
                'jp' => '報告',
                'fields' => [
                    ['short' => 'Mục tiêu', 'key' => 'goals_today'],
                    ['short' => 'Tiến độ', 'key' => 'progress_update'],
                ],
            ],
            [
                'jp' => '連絡',
                'fields' => [
                    ['short' => 'Vướng mắc', 'key' => 'blockers', 'optional' => true],
                ],
            ],
            [
                'jp' => '相談',
                'fields' => [
                    ['short' => 'Kaizen', 'key' => 'improvement_suggestions', 'optional' => true],
                ],
            ],
            [
                'jp' => '計画',
                'fields' => [
                    ['short' => 'Ngày mai', 'key' => 'plan_tomorrow'],
                ],
            ],
        ];
    }

    public function format(DailyReport $report, DailyReportScore $score): string
    {
        $report->loadMissing('employee');
        $score->loadMissing('reviewer');

        $total = number_format((float) $score->total_score, 2, '.', '');
        $grade = $score->grade;

        $lines = $this->beginMessage($report, $this->bracket('Đã duyệt').' '.$this->bracket('HORENSO'));
        $this->appendHorensoBlocks($lines, $report);

        $lines[] = $this->bracket('Đánh giá').' <b>'.$this->escape($grade->value).'</b> '
            .$this->bracket($total).' · '.$this->escape($score->reviewer?->full_name ?? '—');

        if (filled($score->notes)) {
            $lines[] = $this->bracket('Nhận xét').' '.$this->escape($this->compactLine((string) $score->notes, 320));
        }

        return $this->finalizeMessage($lines, $report);
    }

    public function formatRejected(DailyReport $report, int $reviewerEmployeeId, string $notes): string
    {
        $report->loadMissing('employee');
        $reviewerName = Employee::query()->find($reviewerEmployeeId)?->full_name ?? '—';

        $lines = $this->beginMessage($report, $this->bracket('Trả lại').' '.$this->bracket('HORENSO'));
        $this->appendHorensoBlocks($lines, $report);

        $lines[] = $this->bracket('Người trả').' '.$this->escape($reviewerName);
        $lines[] = $this->bracket('Lý do').' '.$this->escape($this->compactLine($notes, 400));

        return $this->finalizeMessage($lines, $report);
    }

    private function bracket(string $label): string
    {
        return '['.$label.']';
    }

    /**
     * @return array<int, string>
     */
    private function beginMessage(DailyReport $report, string $statusLine): array
    {
        $employeeName = $report->employee?->full_name ?? '—';
        $employeeCode = $report->employee?->code;

        $who = $employeeCode
            ? $this->escape($employeeName).' '.$this->bracket($employeeCode)
            : $this->escape($employeeName);

        $dateLabel = $report->date->format('d/m/Y');
        $meta = $this->bracket('Ngày').' '.$dateLabel.$this->projectSuffix($report);
        if ($report->is_late) {
            $meta .= ' '.$this->bracket('Nộp trễ');
        }

        $lines = [
            $statusLine,
            $this->bracket('Nhân viên').' '.$who.' · '.$meta,
        ];

        if (filled($report->title)) {
            $lines[] = $this->bracket('Tiêu đề').' '.$this->escape($this->compactLine((string) $report->title, 200));
        }

        return $lines;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendHorensoBlocks(array &$lines, DailyReport $report): void
    {
        foreach ($this->horensoBlocks() as $block) {
            $entries = [];
            foreach ($block['fields'] as $field) {
                $plain = $this->htmlToPlain($report->{$field['key']} ?? null);
                $optional = (bool) ($field['optional'] ?? false);

                if ($plain === null) {
                    if ($optional) {
                        continue;
                    }
                    $entries[] = $this->bracket($field['short']).' —';

                    continue;
                }

                $entries[] = $this->bracket($field['short']).' '
                    .$this->escape($this->compactLine($plain, self::FIELD_MAX));
            }

            if ($entries === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '<b>'.$this->bracket($block['jp']).'</b>';
            array_push($lines, ...$entries);
        }
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function finalizeMessage(array $lines, DailyReport $report): string
    {
        $url = route('daily-reports.show', $report);

        $lines[] = '';
        $lines[] = '<a href="'.$this->escapeAttr($url).'">'.$this->bracket('Chi tiết').'</a>';

        $text = implode("\n", $lines);

        if (mb_strlen($text) > self::MESSAGE_MAX) {
            $text = mb_substr($text, 0, self::MESSAGE_MAX - 16)."\n".$this->bracket('Rút gọn').' …';
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
            if (is_string($code) && $code !== '') {
                $labels[] = $code;
            }
        }

        if ($labels === []) {
            return '';
        }

        return ' · '.$this->bracket('Dự án').' '.implode(', ', array_unique($labels));
    }

    private function compactLine(string $value, int $max): string
    {
        $line = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return $this->truncate($line, $max);
    }

    private function htmlToPlain(?string $html): ?string
    {
        if (! filled($html)) {
            return null;
        }

        $text = (string) $html;
        $text = preg_replace('/<\/p>\s*<p[^>]*>/i', ' ', $text) ?? $text;
        $text = preg_replace('/<br\s*\/?>/i', ' ', $text) ?? $text;
        $text = preg_replace('/<\/li>\s*/i', ' ', $text) ?? $text;
        $text = preg_replace('/<li[^>]*>/i', '- ', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
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
