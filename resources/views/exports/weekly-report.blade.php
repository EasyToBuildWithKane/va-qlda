<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1e293b; font-size: 12px; line-height: 1.5; }
        h1 { color: #9A0036; font-size: 20px; margin: 0 0 2px; }
        h2 { color: #9A0036; font-size: 13px; text-transform: uppercase; letter-spacing: .5px; margin: 18px 0 6px; border-bottom: 1px solid #FDF2F6; padding-bottom: 3px; }
        .muted { color: #64748b; font-size: 11px; }
        .summary { background: #FDF2F6; border: 1px solid #f3d4df; border-radius: 6px; padding: 10px 12px; margin-top: 10px; }
        .insight { background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 6px; padding: 10px 12px; }
        table { width: 100%; border-collapse: collapse; }
        .kpi td { border: 1px solid #e2e8f0; padding: 6px 8px; width: 25%; }
        .kpi .label { color: #64748b; font-size: 10px; text-transform: uppercase; }
        .kpi .value { font-size: 15px; font-weight: bold; color: #0f172a; }
        ul { margin: 4px 0; padding-left: 16px; }
        li { margin-bottom: 3px; }
        .risk-high { color: #e11d48; font-weight: bold; }
        .risk-med { color: #d97706; font-weight: bold; }
        .fb td { border: 1px solid #e2e8f0; padding: 6px; text-align: center; width: 20%; }
        .fb .n { font-size: 16px; font-weight: bold; }
        .footer { margin-top: 22px; color: #94a3b8; font-size: 10px; border-top: 1px solid #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>Báo cáo tuần</h1>
    <div class="muted">
        {{ $data['project'] }} · {{ $data['sprint'] }} · {{ $data['period'] }} · {{ $data['status_label'] }}
    </div>

    @if($data['executive_summary'])
        <div class="summary">
            <strong style="color:#9A0036">Tóm tắt điều hành</strong><br>
            {{ $data['executive_summary'] }}
        </div>
    @endif

    <h2>Chỉ số chính</h2>
    <table class="kpi">
        @foreach(array_chunk($data['kpi'], 4) as $row)
            <tr>
                @foreach($row as $cell)
                    <td><div class="label">{{ $cell['label'] }}</div><div class="value">{{ $cell['value'] }}</div></td>
                @endforeach
            </tr>
        @endforeach
    </table>

    @if($data['ai_summary'])
        <h2>Nhận định</h2>
        <div class="insight">{{ $data['ai_summary'] }}</div>
    @endif

    @foreach($data['cards'] as $card)
        <h2>{{ $card['label'] }}</h2>
        @if(count($card['lines']))
            <ul>@foreach($card['lines'] as $line)<li>{{ $line }}</li>@endforeach</ul>
        @else
            <div class="muted">Chưa có nội dung.</div>
        @endif
    @endforeach

    <h2>Đánh giá rủi ro</h2>
    @if(count($data['risks']))
        <ul>
            @foreach($data['risks'] as $r)
                <li><span class="{{ $r['level'] === 'Cao' ? 'risk-high' : ($r['level'] === 'Trung bình' ? 'risk-med' : '') }}">[{{ $r['level'] }}]</span> {{ $r['label'] }} — <span class="muted">{{ $r['reason'] }}</span></li>
            @endforeach
        </ul>
    @else
        <div class="muted">Không có rủi ro đáng kể trong tuần.</div>
    @endif

    @if(count($data['feedback']))
        <h2>Tổng hợp phản hồi</h2>
        <table class="fb">
            <tr>
                @foreach($data['feedback'] as $b)
                    <td><div class="n">{{ $b['count'] }}</div><div class="muted">{{ $b['label'] }}</div></td>
                @endforeach
            </tr>
        </table>
    @endif

    @if(count($data['activity']))
        <h2>Sự kiện nổi bật</h2>
        <ul>@foreach($data['activity'] as $line)<li>{{ $line }}</li>@endforeach</ul>
    @endif

    <div class="footer">
        Mã: {{ $data['code'] }}
        @if($data['generated_at']) · Tổng hợp {{ $data['generated_at'] }}@endif
        @if($data['approved_by']) · Duyệt bởi {{ $data['approved_by'] }} ({{ $data['approved_at'] }})@endif
    </div>
</body>
</html>
