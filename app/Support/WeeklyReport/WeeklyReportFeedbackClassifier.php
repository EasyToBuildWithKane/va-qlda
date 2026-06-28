<?php

namespace App\Support\WeeklyReport;

use App\Models\Feedback;
use App\Support\Enums\FeedbackCategory;
use Illuminate\Support\Collection;

/**
 * Phân loại Phản hồi thành các nhóm báo cáo quản trị:
 * Tích cực / Góp ý / Phàn nàn / Lỗi / Yêu cầu thay đổi.
 */
class WeeklyReportFeedbackClassifier
{
    private const BUCKETS = [
        'positive' => ['label' => 'Tích cực', 'color' => 'emerald'],
        'suggestion' => ['label' => 'Góp ý', 'color' => 'sky'],
        'complaint' => ['label' => 'Phàn nàn', 'color' => 'rose'],
        'bug' => ['label' => 'Lỗi', 'color' => 'amber'],
        'change_request' => ['label' => 'Yêu cầu thay đổi', 'color' => 'violet'],
    ];

    /**
     * @return array{breakdown: array<int, array{key:string,label:string,color:string,count:int}>, total:int}
     */
    public function classify(Collection $feedbacks): array
    {
        $counts = array_fill_keys(array_keys(self::BUCKETS), 0);

        foreach ($feedbacks as $feedback) {
            $counts[$this->bucketFor($feedback)]++;
        }

        $breakdown = [];
        foreach (self::BUCKETS as $key => $meta) {
            $breakdown[] = [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'count' => $counts[$key],
            ];
        }

        return [
            'breakdown' => $breakdown,
            'total' => $feedbacks->count(),
        ];
    }

    public function bucketFor(Feedback $feedback): string
    {
        if ($this->looksLikeBug($feedback)) {
            return 'bug';
        }

        return match ($feedback->category) {
            FeedbackCategory::Praise => 'positive',
            FeedbackCategory::Complaint => 'complaint',
            FeedbackCategory::FeatureRequest => 'change_request',
            FeedbackCategory::Improvement, FeedbackCategory::Question, FeedbackCategory::Other => $this->byRating($feedback),
        };
    }

    private function byRating(Feedback $feedback): string
    {
        if ($feedback->rating !== null && $feedback->rating >= 4) {
            return 'positive';
        }

        if ($feedback->rating !== null && $feedback->rating <= 2) {
            return 'complaint';
        }

        return 'suggestion';
    }

    private function looksLikeBug(Feedback $feedback): bool
    {
        $haystack = mb_strtolower($feedback->title.' '.$feedback->description);

        foreach (['bug', 'lỗi', 'crash', 'sự cố', 'không hoạt động'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
