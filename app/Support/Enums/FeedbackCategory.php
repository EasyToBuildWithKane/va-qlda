<?php

namespace App\Support\Enums;

enum FeedbackCategory: string
{
    case FeatureRequest = 'feature_request';
    case Improvement = 'improvement';
    case Complaint = 'complaint';
    case Praise = 'praise';
    case Question = 'question';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FeatureRequest => 'Đề xuất tính năng',
            self::Improvement => 'Cải tiến',
            self::Complaint => 'Phàn nàn',
            self::Praise => 'Khen ngợi',
            self::Question => 'Câu hỏi',
            self::Other => 'Khác',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FeatureRequest => 'violet',
            self::Improvement => 'sky',
            self::Complaint => 'rose',
            self::Praise => 'emerald',
            self::Question => 'amber',
            self::Other => 'slate',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /** @return array<int, array{value:string, label:string, color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'color' => $c->color(),
        ], self::cases());
    }
}
