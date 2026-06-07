<div class="proposal-preview-root">
    <style>
        @include('pdf.partials.ai-purchase-proposal-styles')

        .proposal-preview-root {
            font-family: 'DejaVu Serif', 'Times New Roman', Georgia, serif;
            font-size: 11pt;
            line-height: 1.75;
            color: #000;
        }

        .proposal-preview-sheet {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.12);
        }

        .proposal-preview-root .page-bg {
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            width: 210mm;
            height: 297mm;
            z-index: 0;
            overflow: hidden;
            line-height: 0;
        }

        .proposal-preview-root .page-bg img {
            display: block;
            width: 210mm;
            height: 297mm;
            margin: 0;
            padding: 0;
            object-fit: fill;
        }

        .proposal-preview-root .doc-content.doc-content-on-bg {
            position: relative;
            z-index: 1;
            box-sizing: border-box;
            width: 100%;
            max-width: none;
            min-height: 297mm;
            margin: 0;
            padding: 42mm 12mm 15mm 14mm;
        }
    </style>

    <div class="proposal-preview-sheet">
        <div class="page-bg" aria-hidden="true">
            <img src="{{ $backgroundImg }}" alt="">
        </div>
        <div class="doc-content doc-content-on-bg">
            @include('pdf.partials.ai-purchase-proposal-body')
        </div>
    </div>
</div>
