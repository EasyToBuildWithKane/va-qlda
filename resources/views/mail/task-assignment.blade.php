<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giao việc</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, sans-serif; line-height: 1.5; color: #1e293b; max-width: 560px; margin: 0 auto; padding: 24px;">
    <p style="font-size: 18px; font-weight: 600; margin: 0 0 16px; color: #9A0036;">Giao công việc mới</p>
    <p>Xin chào <strong>{{ $assignee->full_name ?? $assignee->name }}</strong>,</p>
    <p>Bạn được giao công việc trong dự án <strong>{{ $task->project->name ?? '' }}</strong>.</p>
    <table style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; border-collapse: collapse;">
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; width: 120px;">Công việc</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;"><strong>{{ $task->title }}</strong></td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Sprint</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">{{ $task->sprint->name ?? '—' }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; color: #64748b;">Hạn</td>
            <td style="padding: 10px 12px;">{{ $task->due_date?->format('d/m/Y') ?? '—' }}</td>
        </tr>
    </table>
    <p style="margin-top: 20px; font-size: 14px;">
        <a href="{{ url('/projects/'.$task->project_id.'?tab=sprints&task='.$task->id) }}" style="color: #9A0036;">Mở công việc</a>
    </p>
    <p style="margin-top: 24px; font-size: 12px; color: #94a3b8;">{{ config('app.name') }} — thông báo tự động</p>
</body>
</html>
