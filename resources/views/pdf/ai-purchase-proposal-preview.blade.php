<div class="proposal-preview-root">
    <style>
        @include('pdf.partials.ai-purchase-proposal-styles')

        .proposal-preview-root {
            font-family: 'DejaVu Serif', 'Times New Roman', Georgia, serif;
            font-size: 11pt;
            line-height: 1.75;
            color: #000;
        }
        .proposal-preview-shell {
            max-height: min(72vh, 720px);
            overflow: auto;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            background: #f1f5f9;
            padding: 12px;
        }
        .proposal-preview-page {
            position: relative;
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 42mm 12mm 15mm 14mm;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .proposal-preview-bg {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            max-width: none;
            object-fit: cover;
            opacity: 1;
            z-index: 0;
            pointer-events: none;
        }
        .proposal-preview-root .doc-content {
            position: relative;
            z-index: 1;
            max-width: 182mm;
            margin: 0 auto;
        }
        .proposal-preview-root .page-bg {
            display: none;
        }
    </style>

    <div class="proposal-preview-shell">
        <div class="proposal-preview-page">
            <img
                class="proposal-preview-bg"
                src="{{ $backgroundImg }}"
                alt=""
            >
            <div class="doc-content">
                @include('pdf.partials.ai-purchase-proposal-body')
            </div>
        </div>
    </div>
</div>
