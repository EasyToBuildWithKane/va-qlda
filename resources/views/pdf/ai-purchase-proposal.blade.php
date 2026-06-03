<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.ai-purchase-proposal-styles')
    </style>
</head>
<body>

    <div class="page-bg">
        <img src="{{ 'file://'.public_path('docx/background.png') }}" alt="">
    </div>

    @php
        $checkboxImg = 'file://'.public_path('docx/checkbox.png');
    @endphp

    <div class="doc-content">
        @include('pdf.partials.ai-purchase-proposal-body')
    </div>

</body>
</html>
