<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tổng hợp sprint</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, sans-serif; line-height: 1.5; color: #1e293b; max-width: 640px; margin: 0 auto; padding: 24px;">
    <p style="font-size: 18px; font-weight: 600; margin: 0 0 16px; color: #9A0036;">Tổng hợp sprint</p>
    <p>Xin chào <strong>{{ $assignee->full_name ?? $assignee->name }}</strong>,</p>
    <p>Công việc trong sprint <strong>{{ $sprint->name }}</strong> — dự án <strong>{{ $project->name }}</strong>:</p>
    {!! $tasksTable !!}
    <p style="margin-top: 16px;">Tổng: {{ $tasks->count() }} công việc.</p>
    <p style="margin-top: 24px; font-size: 12px; color: #94a3b8;">{{ config('app.name') }} — thông báo tự động</p>
</body>
</html>
