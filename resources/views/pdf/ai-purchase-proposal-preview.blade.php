<div class="proposal-preview-root" data-background-url="{{ $backgroundImg }}">
    <style>
        @include('pdf.partials.ai-purchase-proposal-styles')

        .proposal-preview-root {
            font-family: 'DejaVu Serif', 'Times New Roman', Georgia, serif;
            font-size: 11pt;
            line-height: 1.75;
            color: #000;
        }

        .proposal-preview-source {
            box-sizing: border-box;
            width: 210mm;
            margin: 0;
            padding: 42mm 12mm 15mm 14mm;
        }

        .proposal-preview-flow {
            width: 100%;
        }
    </style>

    <div class="proposal-preview-source">
        <div class="proposal-preview-flow doc-content doc-content-on-bg">
            @include('pdf.partials.ai-purchase-proposal-body')
        </div>
    </div>
</div>
