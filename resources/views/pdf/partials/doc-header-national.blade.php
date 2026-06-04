@php
    $docDateLine = trim((string) ($docDate ?? ''));
@endphp

<table class="doc-header-national">
    <tr>
        <td class="header-national-spacer"></td>
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
