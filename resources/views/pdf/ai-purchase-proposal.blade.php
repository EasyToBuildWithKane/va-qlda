<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        @include('pdf.partials.ai-purchase-proposal-styles')
        @include('pdf.partials.ai-payment-request-styles')
    </style>
</head>
<body>

    @include('pdf.partials.page-background')

    @php
        $checkboxImg = 'file://'.public_path('docx/checkbox.png');
    @endphp

    <div class="doc-content doc-content-on-bg">
        @include('pdf.partials.ai-purchase-proposal-body')
    </div>

</body>
</html>
