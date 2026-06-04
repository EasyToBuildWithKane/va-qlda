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

    <div class="page-bg">
        <img src="{{ 'file://'.public_path('docx/background.png') }}" alt="">
    </div>

    <div class="doc-content">
        @include('pdf.partials.ai-payment-request-body')
    </div>

</body>
</html>
