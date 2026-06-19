<?php

namespace App\Support;

use App\Domain\DailyReport\Models\DailyReport;
use App\Domain\DailyReport\Models\DailyReportScore;
use App\Models\AiPurchaseProposal;
use App\Models\Blocker;
use App\Models\CoachingAssignment;
use App\Models\CoachingSession;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Feedback;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\SystemAccount;
use App\Models\Task;
use App\Models\Vendor;
use App\Services\NotificationService;
use App\Support\Enums\NotificationType;

/**
 * Bridges domain activity loggers/controllers to the notification inbox.
 */
class NotificationDispatcher
{
    public static function service(): NotificationService
    {
        return app(NotificationService::class);
    }

    public static function taskCreated(Task $task, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $ref = $svc->taskRef($task);
        $title = $actor
            ? "{$actor->display_name} tạo {$ref}"
            : "Công việc mới {$ref}";

        $svc->notifyTaskStakeholders(
            $task,
            NotificationType::TaskCreated,
            $title,
            $task->title,
            $actor,
        );

        if ($task->assignee_id && $actor) {
            $assignee = $svc->accountsForEmployees([$task->assignee_id])->first();
            if ($assignee && $assignee->id !== $actor->id) {
                $svc->notify(
                    [$assignee],
                    NotificationType::TaskAssigned,
                    "Bạn được giao {$ref}",
                    $task->title,
                    self::taskContext($task, $actor),
                );
            }
        }
    }

    public static function taskUpdated(Task $task, ?SystemAccount $actor, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $svc = self::service();
        $ref = $svc->taskRef($task);

        if (isset($changes['due_date'])) {
            $detail = 'Hạn mới: '.($task->due_date?->format('d/m/Y') ?? '—');
            $svc->notifyTaskStakeholders(
                $task,
                NotificationType::TaskDeadlineChanged,
                "{$ref} — thay đổi hạn",
                self::taskNotificationBody($task, $detail),
                $actor,
            );

            return;
        }

        if (isset($changes['assignee_id'])) {
            $assignee = $svc->accountsForEmployees([(int) $changes['assignee_id']])->first();
            if ($assignee) {
                $svc->notify(
                    [$assignee],
                    NotificationType::TaskAssigned,
                    "Bạn được giao {$ref}",
                    $task->title,
                    self::taskContext($task, $actor),
                );
            }
        }

        $summary = NotificationChangeSummary::task($changes);
        $body = self::taskNotificationBody($task, $summary);

        $svc->notifyTaskStakeholders(
            $task,
            NotificationType::TaskUpdated,
            $actor ? "{$actor->display_name} cập nhật {$ref}" : "Cập nhật {$ref}",
            $body,
            $actor,
        );
    }

    public static function taskStatusChanged(Task $task, string $from, string $to, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $ref = $svc->taskRef($task);
        $type = $to === 'done' ? NotificationType::TaskCompleted : NotificationType::TaskStatusChanged;
        $title = $actor
            ? "{$actor->display_name} đổi trạng thái {$ref}"
            : "Thay đổi trạng thái {$ref}";

        $body = self::taskNotificationBody($task, "Trạng thái: {$from} → {$to}");

        $svc->notifyTaskStakeholders(
            $task,
            $type,
            $title,
            $body,
            $actor,
        );
    }

    public static function taskComment(Task $task, ?SystemAccount $actor, bool $isMention = false): void
    {
        $svc = self::service();
        $ref = $svc->taskRef($task);
        $type = $isMention ? NotificationType::CommentMention : NotificationType::CommentTaskThread;

        $svc->notifyTaskStakeholders(
            $task,
            $type,
            $actor ? "{$actor->display_name} — bình luận {$ref}" : "Bình luận mới {$ref}",
            null,
            $actor,
        );
    }

    public static function projectCreated(Project $project, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $title = $actor
            ? "{$actor->display_name} tạo dự án {$project->name}"
            : "Dự án mới: {$project->name}";

        if ($actor) {
            $svc->notifyAdmins(NotificationType::ProjectCreated, $title, $project->code, [
                'actor' => $actor,
                'project_id' => $project->id,
                'action_url' => "/projects/{$project->id}",
            ]);
        }
    }

    public static function projectUpdated(Project $project, ?SystemAccount $actor, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $svc = self::service();
        $employeeIds = $project->members()->pluck('employees.id')->all();
        $members = $svc->accountsForEmployees($employeeIds);

        $type = match (true) {
            isset($changes['manager_id']) => NotificationType::ProjectPmChanged,
            isset($changes['due_date']) => NotificationType::ProjectDeadlineChanged,
            isset($changes['status']) => NotificationType::ProjectStatusChanged,
            default => NotificationType::ProjectStatusChanged,
        };

        $title = $actor
            ? "{$actor->display_name} cập nhật dự án {$project->name}"
            : "Dự án {$project->name} được cập nhật";

        $body = NotificationChangeSummary::project($changes);

        $svc->notify($members, $type, $title, $body, [
            'actor' => $actor,
            'project_id' => $project->id,
            'action_url' => "/projects/{$project->id}",
        ]);

        if ($actor) {
            $svc->notifyAdmins(NotificationType::AdminUserAction, $title, $body, [
                'actor' => $actor,
                'project_id' => $project->id,
                'action_url' => "/projects/{$project->id}",
            ]);
        }
    }

    public static function sprintChanged(
        Project $project,
        Sprint $sprint,
        string $verb,
        ?SystemAccount $actor,
    ): void {
        $svc = self::service();
        $members = $svc->accountsForEmployees($project->members()->pluck('employees.id')->all());
        $title = $actor
            ? "{$actor->display_name} {$verb} sprint {$sprint->name}"
            : "Sprint {$sprint->name} — {$verb}";

        $type = match ($verb) {
            'tạo' => NotificationType::SprintCreated,
            'cập nhật' => NotificationType::SprintUpdated,
            'xoá' => NotificationType::SprintDeleted,
            default => NotificationType::SprintEnded,
        };

        $svc->notify($members, $type, $title, $project->name, [
            'actor' => $actor,
            'project_id' => $project->id,
            'sprint_id' => $sprint->id,
            'action_url' => "/projects/{$project->id}?tab=sprints",
        ]);
    }

    public static function blockerCreated(Blocker $blocker, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $members = self::blockerNotifyAccounts($blocker, $actor);
        if ($members->isEmpty()) {
            return;
        }

        $ref = self::blockerRef($blocker);
        $title = $actor
            ? "{$actor->display_name} ghi nhận {$ref}"
            : "Vướng mắc mới {$ref}";

        $svc->notify($members, NotificationType::BlockerCreated, $title, $blocker->title, [
            'actor' => $actor,
            'project_id' => $blocker->project_id,
            'entity_type' => 'blocker',
            'entity_id' => $blocker->id,
            'action_url' => '/blockers',
        ]);
    }

    public static function blockerUpdated(Blocker $blocker, ?SystemAccount $actor, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $svc = self::service();
        $members = self::blockerNotifyAccounts($blocker, $actor);
        if ($members->isEmpty()) {
            return;
        }

        $ref = self::blockerRef($blocker);
        $title = $actor
            ? "{$actor->display_name} cập nhật {$ref}"
            : "Cập nhật {$ref}";

        $body = trim(($blocker->title ? $blocker->title."\n" : '').(NotificationChangeSummary::blocker($changes) ?? ''));

        $svc->notify($members, NotificationType::BlockerUpdated, $title, $body !== '' ? $body : null, [
            'actor' => $actor,
            'project_id' => $blocker->project_id,
            'entity_type' => 'blocker',
            'entity_id' => $blocker->id,
            'action_url' => '/blockers',
        ]);
    }

    public static function blockerComment(Blocker $blocker, ?SystemAccount $actor, bool $isMention = false): void
    {
        $svc = self::service();
        $members = self::blockerNotifyAccounts($blocker, $actor);
        if ($members->isEmpty()) {
            return;
        }

        $ref = self::blockerRef($blocker);
        $type = $isMention ? NotificationType::CommentMention : NotificationType::CommentBlockerThread;
        $title = $actor
            ? "{$actor->display_name} — trao đổi {$ref}"
            : "Trao đổi mới {$ref}";

        $svc->notify($members, $type, $title, $blocker->title, [
            'actor' => $actor,
            'project_id' => $blocker->project_id,
            'entity_type' => 'blocker',
            'entity_id' => $blocker->id,
            'action_url' => '/blockers',
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, SystemAccount>
     */
    private static function blockerNotifyAccounts(Blocker $blocker, ?SystemAccount $actor): \Illuminate\Support\Collection
    {
        $blocker->loadMissing('project');
        $employeeIds = collect([$blocker->owner_id, $blocker->raised_by_id]);
        if ($blocker->project_id) {
            $employeeIds = $employeeIds->merge($blocker->project?->members()->pluck('employees.id') ?? []);
        }

        return self::service()->accountsForEmployees(
            $employeeIds->filter()->unique()->values()->all(),
        )->reject(fn (SystemAccount $a) => $actor && $a->id === $actor->id);
    }

    private static function blockerRef(Blocker $blocker): string
    {
        return $blocker->code ?? ('RSK-'.$blocker->id);
    }

    public static function feedbackChanged(Feedback $feedback, string $verb, ?SystemAccount $actor, ?array $changes = null): void
    {
        $svc = self::service();
        $ref = $feedback->code ?? ('FB-'.$feedback->id);
        $title = $actor
            ? "{$actor->display_name} {$verb} {$ref}"
            : "{$verb} {$ref}";

        $body = $changes ? NotificationChangeSummary::feedback($changes) : $feedback->title;
        $type = str_contains($verb, 'tạo') ? NotificationType::FeedbackCreated : NotificationType::FeedbackUpdated;
        $context = [
            'actor' => $actor,
            'project_id' => $feedback->project_id,
            'entity_type' => 'feedback',
            'entity_id' => $feedback->id,
            'action_url' => $feedback->project_id
                ? "/projects/{$feedback->project_id}?tab=feedback"
                : "/feedback/{$feedback->id}",
        ];

        $recipients = $svc->accountsForEmployees(array_filter([$feedback->assignee_id, $feedback->reporter_employee_id]));

        $svc->notify($recipients, $type, $title, $body, $context);
    }

    public static function feedbackComment(Feedback $feedback, ?SystemAccount $actor, bool $isMention = false): void
    {
        $svc = self::service();
        $ref = $feedback->code ?? ('FB-'.$feedback->id);
        $type = $isMention ? NotificationType::CommentMention : NotificationType::CommentFeedbackThread;
        $title = $actor
            ? "{$actor->display_name} — bình luận {$ref}"
            : "Bình luận mới {$ref}";

        $recipients = $svc->accountsForEmployees(array_filter([$feedback->assignee_id, $feedback->reporter_employee_id]));

        $svc->notify($recipients, $type, $title, $feedback->title, [
            'actor' => $actor,
            'project_id' => $feedback->project_id,
            'entity_type' => 'feedback',
            'entity_id' => $feedback->id,
            'action_url' => $feedback->project_id
                ? "/projects/{$feedback->project_id}?tab=feedback"
                : "/feedback/{$feedback->id}",
        ]);
    }

    public static function projectMemberAdded(Project $project, Employee $member, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $account = $svc->accountsForEmployees([$member->id])->first();
        if (! $account || ($actor && $account->id === $actor->id)) {
            return;
        }

        $title = $actor
            ? "{$actor->display_name} thêm bạn vào dự án {$project->name}"
            : "Bạn được thêm vào dự án {$project->name}";

        $svc->notify([$account], NotificationType::ProjectMemberAdded, $title, $project->code, [
            'actor' => $actor,
            'project_id' => $project->id,
            'action_url' => "/projects/{$project->id}",
        ]);
    }

    public static function projectArchived(Project $project, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $members = $svc->accountsForEmployees($project->members()->pluck('employees.id')->all());
        $title = $actor
            ? "{$actor->display_name} lưu trữ dự án {$project->name}"
            : "Dự án {$project->name} đã lưu trữ";

        $svc->notify($members, NotificationType::ProjectArchived, $title, $project->code, [
            'actor' => $actor,
            'project_id' => $project->id,
            'action_url' => "/projects/{$project->id}",
        ]);
    }

    public static function contractRenewed(Contract $successor, Contract $predecessor, ?SystemAccount $actor): void
    {
        if ($actor === null) {
            return;
        }

        $title = "{$actor->display_name} gia hạn hợp đồng {$predecessor->code}";
        $body = trim("Phụ lục: {$successor->code}\nHết hạn mới: ".($successor->expiry_date?->format('d/m/Y') ?? '—'));

        self::notifyContractStakeholders(
            $predecessor,
            $actor,
            NotificationType::SystemContractRenewed,
            $title,
            $body,
        );
    }

    public static function aiProposalDecision(AiPurchaseProposal $proposal, string $decision, ?SystemAccount $actor): void
    {
        if ($actor === null) {
            return;
        }

        $svc = self::service();
        $creator = SystemAccount::find($proposal->created_by);
        if (! $creator || $creator->id === $actor->id) {
            return;
        }

        $type = $decision === 'approved' ? NotificationType::AiProposalApproved : NotificationType::AiProposalRejected;
        $verb = $decision === 'approved' ? 'được duyệt' : 'bị từ chối';
        $tool = $proposal->tool_name ?? ($proposal->proposal_code ?? 'AI');
        $title = "Phiếu đề xuất {$tool} {$verb}";
        $body = $decision === 'approved'
            ? 'Tiến hành đăng ký tài khoản sau khi thanh toán được duyệt.'
            : ($proposal->rejection_reason ?? null);

        $svc->notify([$creator], $type, $title, $body, [
            'actor' => $actor,
            'entity_type' => 'ai_proposal',
            'entity_id' => $proposal->id,
            'action_url' => '/ai-accounts',
        ]);
    }

    public static function dailyReportSubmitted(DailyReport $report, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $employee = $report->employee;
        $name = $employee?->name ?? 'Nhân viên';
        $date = $report->date->format('d/m/Y');
        $title = "Báo cáo ngày {$date} của {$name} chờ duyệt";

        $svc->notifyAdmins(NotificationType::DailyReportSubmitted, $title, null, [
            'actor' => $actor,
            'entity_type' => 'daily_report',
            'entity_id' => $report->id,
            'action_url' => "/daily-reports/{$report->uuid}",
        ]);
    }

    public static function dailyReportRecalled(DailyReport $report, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $employee = $report->employee;
        $name = $employee?->name ?? 'Nhân viên';
        $date = $report->date->format('d/m/Y');
        $title = "{$name} đã rút lại báo cáo ngày {$date} để chỉnh sửa";

        $svc->notifyAdmins(NotificationType::DailyReportRecalled, $title, null, [
            'actor' => $actor,
            'entity_type' => 'daily_report',
            'entity_id' => $report->id,
            'action_url' => "/daily-reports/{$report->uuid}",
        ]);
    }

    public static function dailyReportScored(DailyReport $report, DailyReportScore $score, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $author = $svc->accountsForEmployees([$report->employee_id])->first();
        if (! $author) {
            return;
        }

        $date = $report->date->format('d/m/Y');
        $grade = $score->grade?->value ?? '—';
        $total = number_format((float) $score->total_score, 1);
        $title = "Báo cáo ngày {$date} đã được chấm: {$total} điểm ({$grade})";

        $svc->notify([$author], NotificationType::DailyReportScored, $title, $score->notes, [
            'actor' => $actor,
            'entity_type' => 'daily_report',
            'entity_id' => $report->id,
            'action_url' => "/daily-reports/{$report->uuid}",
        ]);
    }

    public static function dailyReportRejected(DailyReport $report, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $author = $svc->accountsForEmployees([$report->employee_id])->first();
        if (! $author) {
            return;
        }

        $date = $report->date->format('d/m/Y');
        $title = "Báo cáo ngày {$date} bị trả lại — cần chỉnh sửa";

        $svc->notify([$author], NotificationType::DailyReportRejected, $title, $report->review_notes, [
            'actor' => $actor,
            'entity_type' => 'daily_report',
            'entity_id' => $report->id,
            'action_url' => "/daily-reports/{$report->uuid}",
        ]);
    }

    public static function coachingSessionChanged(CoachingSession $session, string $verb, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $session->loadMissing('course');
        $course = $session->course;
        if (! $course?->student_id) {
            return;
        }

        $students = $svc->accountsForEmployees([$course->student_id]);
        if ($students->isEmpty()) {
            return;
        }

        $type = $verb === 'tạo' ? NotificationType::CoachingSessionCreated : NotificationType::CoachingSessionUpdated;
        $title = $verb === 'tạo'
            ? "Buổi học mới: {$session->title}"
            : "Buổi học cập nhật: {$session->title}";
        $date = $session->date?->format('d/m/Y');
        $body = $date ? "Ngày: {$date}" : null;

        $svc->notify($students, $type, $title, $body, [
            'actor' => $actor,
            'entity_type' => 'coaching_session',
            'entity_id' => $session->id,
            'action_url' => route('coaching.sessions.show', ['session' => $session->id]),
        ]);
    }

    public static function coachingAssignmentCreated(CoachingAssignment $assignment, ?SystemAccount $actor): void
    {
        $svc = self::service();
        $assignment->loadMissing('session.course');
        $course = $assignment->session?->course;
        if (! $course?->student_id) {
            return;
        }

        $students = $svc->accountsForEmployees([$course->student_id]);
        if ($students->isEmpty()) {
            return;
        }

        $title = "Bài tập mới: {$assignment->title}";
        $deadline = $assignment->deadline?->format('d/m/Y');
        $body = $deadline ? "Hạn nộp: {$deadline}" : null;

        $svc->notify($students, NotificationType::CoachingAssignmentCreated, $title, $body, [
            'actor' => $actor,
            'entity_type' => 'coaching_assignment',
            'entity_id' => $assignment->id,
            'action_url' => route('coaching.sessions.show', ['session' => $assignment->session_id]),
        ]);
    }

    private static function taskNotificationBody(Task $task, ?string $detail = null): string
    {
        $lines = array_filter([
            trim((string) $task->title),
            $detail !== null && $detail !== '' ? trim($detail) : null,
        ]);

        return $lines !== [] ? implode("\n", $lines) : '—';
    }

    /** @return array<string, mixed> */
    private static function taskContext(Task $task, ?SystemAccount $actor): array
    {
        return [
            'actor' => $actor,
            'project_id' => $task->project_id,
            'sprint_id' => $task->sprint_id,
            'task_id' => $task->id,
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'action_url' => "/projects/{$task->project_id}?task={$task->id}",
            'meta' => ['task_ref' => 'TASK-'.$task->id, 'task_title' => $task->title],
        ];
    }

    public static function contractCreated(Contract $contract, ?SystemAccount $actor): void
    {
        if ($actor === null) {
            return;
        }

        $title = "{$actor->display_name} tạo hợp đồng {$contract->code}";
        self::notifyContractStakeholders(
            $contract,
            $actor,
            NotificationType::SystemContractCreated,
            $title,
            $contract->name,
        );
    }

    public static function contractUpdated(Contract $contract, ?SystemAccount $actor, array $changes): void
    {
        if ($actor === null || $changes === []) {
            return;
        }

        $title = "{$actor->display_name} cập nhật hợp đồng {$contract->code}";
        $fields = implode(', ', array_keys($changes));
        $body = trim($contract->name."\n".$fields);

        self::notifyContractStakeholders(
            $contract,
            $actor,
            NotificationType::SystemContractUpdated,
            $title,
            $body !== '' ? $body : null,
        );
    }

    /**
     * @param  'tạo'|'cập nhật'|'xoá'  $verb
     */
    public static function vendorReview(
        Vendor $vendor,
        string $verb,
        ?SystemAccount $actor,
        ?float $totalScore = null,
        ?Contract $contract = null,
    ): void {
        if ($actor === null) {
            return;
        }

        $title = "{$actor->display_name} {$verb} đánh giá NCC {$vendor->name}";
        $body = $totalScore !== null ? "Điểm trung bình {$totalScore}/10" : null;

        if ($contract !== null) {
            self::notifyContractStakeholders(
                $contract,
                $actor,
                NotificationType::SystemContractVendorReview,
                $title,
                $body,
            );

            return;
        }

        $svc = self::service();
        $context = [
            'actor' => $actor,
            'entity_type' => 'vendor',
            'entity_id' => $vendor->id,
            'action_url' => "/contracts/vendors/{$vendor->id}",
        ];

        $svc->notifyAdmins(NotificationType::SystemContractVendorReview, $title, $body, $context);
    }

    private static function notifyContractStakeholders(
        Contract $contract,
        SystemAccount $actor,
        NotificationType $type,
        string $title,
        ?string $body,
    ): void {
        $svc = self::service();
        $context = [
            'actor' => $actor,
            'entity_type' => 'contract',
            'entity_id' => $contract->id,
            'action_url' => "/contracts/{$contract->id}",
        ];

        $recipients = $svc->accountsForEmployees(array_filter([
            $contract->owner_id,
            $contract->manager_id,
        ]))
            ->reject(fn (SystemAccount $a) => $a->id === $actor->id)
            ->unique('id');

        $svc->notify($recipients, $type, $title, $body, $context);
        $svc->notifyAdmins($type, $title, $body, $context, $recipients->pluck('id')->all());
    }
}
