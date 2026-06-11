<?php

namespace App\Services\Telegram;

use App\Models\Blocker;
use App\Models\SystemAccount;
use App\Support\Enums\BlockerStatus;

class BlockerResolvedTelegramFormatter
{
    private const FIELD_MAX = 480;

    private const MESSAGE_MAX = 4000;

    public function format(Blocker $blocker, SystemAccount $actor, BlockerStatus $newStatus): string
    {
        $headline = match ($newStatus) {
            BlockerStatus::Resolved => 'Vướng mắc đã xử lý',
            BlockerStatus::Closed => 'Vướng mắc đã đóng',
            default => 'Vướng mắc cập nhật',
        };

        $actor->loadMissing('employee');
        $confirmedBy = $actor->employee?->full_name ?? '—';

        $lines = [
            '<b>'.$this->escape($headline).'</b>',
        ];

        if (filled($blocker->code)) {
            $lines[] = $this->bracket('Mã').' '.$this->escape((string) $blocker->code);
        }

        $lines[] = $this->bracket('Tiêu đề').' '.$this->escape($this->compactLine((string) $blocker->title, 240));
        $lines[] = $this->bracket('Trạng thái').' <b>'.$this->escape($newStatus->labelVi()).'</b>';
        $lines[] = $this->bracket('Mức độ').' '.$this->escape($blocker->severity->label());

        $projectLabel = $blocker->project?->code ?? $blocker->project?->name;
        if (filled($projectLabel)) {
            $lines[] = $this->bracket('Dự án').' '.$this->escape((string) $projectLabel);
        }

        if ($blocker->owner) {
            $lines[] = $this->bracket('Người xử lý').' '.$this->escape($blocker->owner->full_name);
        }

        if ($blocker->raisedBy) {
            $lines[] = $this->bracket('Người ghi nhận').' '.$this->escape($blocker->raisedBy->full_name);
        }

        $lines[] = $this->bracket('Xác nhận bởi').' '.$this->escape($confirmedBy);

        $this->appendOptionalField($lines, 'Nguyên nhân', $blocker->root_cause);
        $this->appendOptionalField($lines, 'Cách xử lý', $blocker->resolution);

        if ($blocker->resolved_at) {
            $lines[] = $this->bracket('Thời điểm').' '.$blocker->resolved_at->format('d/m/Y H:i');
        }

        $url = route('blockers.index', array_filter([
            'q' => $blocker->code ?: $blocker->title,
        ]));

        $lines[] = '';
        $lines[] = '<a href="'.$this->escapeAttr($url).'">'.$this->bracket('Xem trên hệ thống').'</a>';

        $text = implode("\n", $lines);

        if (mb_strlen($text) > self::MESSAGE_MAX) {
            $text = mb_substr($text, 0, self::MESSAGE_MAX - 16)."\n".$this->bracket('Rút gọn').' …';
        }

        return $text;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function appendOptionalField(array &$lines, string $label, ?string $value): void
    {
        if (! filled($value)) {
            return;
        }

        $plain = $this->htmlToPlain($value) ?? trim(strip_tags((string) $value));
        if ($plain === '') {
            return;
        }

        $lines[] = $this->bracket($label).' '.$this->escape($this->compactLine($plain, self::FIELD_MAX));
    }

    private function bracket(string $label): string
    {
        return '['.$label.']';
    }

    private function compactLine(string $value, int $max): string
    {
        $line = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        if (mb_strlen($line) <= $max) {
            return $line;
        }

        return mb_substr($line, 0, $max - 1).'…';
    }

    private function htmlToPlain(?string $html): ?string
    {
        if (! filled($html)) {
            return null;
        }

        $text = (string) $html;
        $text = preg_replace('/<\/p>\s*<p[^>]*>/i', ' ', $text) ?? $text;
        $text = preg_replace('/<br\s*\/?>/i', ' ', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $text === '' ? null : $text;
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
