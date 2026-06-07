<div class="proposal-preview-root">
    <style>
        @include('pdf.partials.ai-purchase-proposal-styles')

        .proposal-preview-root {
            font-family: 'DejaVu Serif', 'Times New Roman', Georgia, serif;
            font-size: 11pt;
            line-height: 1.75;
            color: #000;
        }

        .proposal-preview-strip {
            position: relative;
            width: 210mm;
            margin: 0 auto;
            isolation: isolate;
            box-sizing: border-box;
        }

        .proposal-preview-strip-bg {
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            z-index: 0;
            pointer-events: none;
            background-image: url('{{ $backgroundImg }}');
            background-size: 100% 297mm;
            background-repeat: repeat-y;
            background-position: top center;
        }

        .proposal-preview-strip-guides {
            position: absolute;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            z-index: 2;
            pointer-events: none;
            background-image: repeating-linear-gradient(
                to bottom,
                transparent 0,
                transparent calc(297mm - 1px),
                rgba(154, 0, 54, 0.14) calc(297mm - 1px),
                rgba(154, 0, 54, 0.14) 297mm
            );
        }

        .proposal-preview-root .doc-content.doc-content-on-bg {
            position: relative;
            z-index: 1;
            box-sizing: border-box;
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 42mm 12mm 15mm 14mm;
        }
    </style>

    <div class="proposal-preview-strip">
        <div
            class="proposal-preview-strip-bg"
            aria-hidden="true"
        ></div>
        <div
            class="proposal-preview-strip-guides"
            aria-hidden="true"
        ></div>
        <div class="doc-content doc-content-on-bg">
            @include('pdf.partials.ai-purchase-proposal-body')
        </div>
    </div>
</div>
