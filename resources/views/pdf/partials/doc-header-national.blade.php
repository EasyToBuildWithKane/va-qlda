@php
    $docDateLine = trim((string) ($docDate ?? ''));
@endphp

<div class="doc-national-block">
    <div class="republic">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
    <div class="motto">
        <span class="motto-line">Độc lập – Tự do – Hạnh phúc</span>
    </div>
    @if($docDateLine !== '')
        <div class="doc-date">{{ $docDateLine }}</div>
    @endif
</div>
