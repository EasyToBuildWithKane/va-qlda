<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\TaskEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailNotificationController extends Controller
{
    public function __construct(private readonly TaskEmailService $taskEmail) {}

    public function dailySummary(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manage', $project);

        if (! $this->taskEmail->isEnabled()) {
            return back()->with('error', 'Gửi email chưa được bật trong Cấu hình hệ thống.');
        }

        $validated = $request->validate([
            'sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
        ]);

        $sprintId = isset($validated['sprint_id']) ? (int) $validated['sprint_id'] : null;
        if ($sprintId !== null && ! $project->sprints()->whereKey($sprintId)->exists()) {
            return back()->with('error', 'Sprint không thuộc dự án này.');
        }

        $count = $this->taskEmail->queueDailySummaries($project, $sprintId);

        if ($count === 0) {
            return back()->with('warning', $this->zeroQueueMessage(
                EmailTemplate::KEY_DAILY_SUMMARY,
                'Không có email nào được xếp hàng (thiếu công việc có người phụ trách, email nhân viên, hoặc không có task cập nhật/hạn hôm nay).',
            ));
        }

        return back()->with('success', "Đã xếp hàng gửi {$count} email tổng hợp trong ngày.");
    }

    public function sprintSummary(Request $request, Project $project, Sprint $sprint): RedirectResponse
    {
        $this->authorize('manage', $project);

        if ((int) $sprint->project_id !== (int) $project->id) {
            abort(404);
        }

        if (! $this->taskEmail->isEnabled()) {
            return back()->with('error', 'Gửi email chưa được bật trong Cấu hình hệ thống.');
        }

        $count = $this->taskEmail->queueSprintSummaries($project, $sprint);

        if ($count === 0) {
            return back()->with('warning', $this->zeroQueueMessage(
                EmailTemplate::KEY_SPRINT_SUMMARY,
                'Không có email nào được xếp hàng (sprint chưa có công việc gốc có người phụ trách hoặc nhân viên thiếu email).',
            ));
        }

        return back()->with('success', "Đã xếp hàng gửi {$count} email tổng hợp sprint.");
    }

    private function zeroQueueMessage(string $templateKey, string $default): string
    {
        $template = EmailTemplate::findByKey($templateKey);
        if ($template !== null && ! $template->is_active) {
            return 'Mẫu email tương ứng đang tắt trong Cấu hình → Email & Thông báo.';
        }

        return $default;
    }
}
