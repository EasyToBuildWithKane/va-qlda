@php
    $schoolHeader = mb_strtoupper((string) ($schoolName ?? 'Hệ Thống Trường Việt Mỹ'), 'UTF-8');
    $deptHeader = mb_strtoupper((string) ($departmentHeader ?? 'Phòng Công Nghệ'), 'UTF-8');
    $formCodeLine = trim((string) ($formCode ?? ''));
    $docDateLine = trim((string) ($docDate ?? ''));
@endphp

@if($formCodeLine !== '')
    <p class="form-code">{{ $formCodeLine }}</p>
@endif

<table class="doc-header">
    <tr>
        <td class="cell-left">
            <span class="doc-header-school">{{ $schoolHeader }}</span><br>
            <span class="unit">—<br><span class="doc-header-dept">{{ $deptHeader }}</span></span>
        </td>
        <td class="cell-right">
            <div class="republic">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
            <div class="motto">
                <span class="motto-line">Độc lập – Tự do – Hạnh phúc</span>
            </div>
            @if($docDateLine !== '')
                <div class="doc-date">{{ $docDateLine }}</div>
            @endif
        </td>
    </tr>
</table>
