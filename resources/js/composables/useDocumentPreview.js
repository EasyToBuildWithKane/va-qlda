import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { renderAsync } from 'docx-preview';
import * as XLSX from 'xlsx-js-style';
import { renderSheetToHtml } from '@/composables/useXlsxRender';

const MAX_OFFICE_BYTES = 15 * 1024 * 1024; // 15 MB
const MAX_TEXT_BYTES = 1 * 1024 * 1024; // 1 MB
const XLSX_PAGE_SIZE = 200;
const TEXT_EXTS = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'yml', 'yaml', 'html', 'htm', 'css', 'js', 'ts', 'vue', 'php', 'ini', 'env'];

export const ZOOM_STEPS = [0.5, 0.65, 0.8, 0.9, 1, 1.1, 1.25, 1.5];

/** className phải là `docx` — khớp selector `section.docx` của thư viện + CSS inject. */
const DOCX_RENDER_OPTIONS = {
    className: 'docx',
    inWrapper: true,
    ignoreWidth: false,
    ignoreHeight: false,
    ignoreFonts: false,
    breakPages: true,
    ignoreLastRenderedPageBreak: true,
    // Bật bộ layout nâng cao của docx-preview 0.4: tự xử lý watermark VML,
    // letterhead và tab-stop — thay cho các heuristic DOM tự viết trước đây.
    experimental: true,
    useBase64URL: true,
    renderHeaders: true,
    renderFooters: true,
    renderFootnotes: true,
    renderEndnotes: true,
    renderAltChunks: true,
    renderComments: false,
    renderChanges: false,
};

/**
 * Canvas trong iframe — tờ giấy A4 trên nền xám, cuộn liên tục.
 * Chỉ lo khung giấy/nền; bố cục bên trong trang để docx-preview tự quyết định.
 */
const DOCX_IFRAME_BASE_STYLES = `
html, body {
  margin: 0;
  padding: 0;
  background: #eceff3;
  color: #0f172a;
  color-scheme: light;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-rendering: optimizeLegibility;
}
html {
  height: 100%;
  overflow-y: auto;
  overflow-x: auto;
}
body {
  min-height: 100%;
  box-sizing: border-box;
}
.docx-fit-host {
  display: block;
  margin: 0 auto;
  overflow: hidden;
}
.docx-fit-inner {
  transform-origin: top left;
  will-change: transform;
}
.docx-wrapper {
  background: transparent !important;
  padding: 28px 24px 40px !important;
  box-sizing: border-box;
  display: flex !important;
  flex-flow: column nowrap !important;
  align-items: center !important;
  gap: 22px;
  width: max-content;
  max-width: none;
}
.docx-wrapper > section.docx {
  background: #fff !important;
  border: 1px solid rgba(15, 23, 42, 0.06);
  box-shadow:
    0 1px 1px rgba(15, 23, 42, 0.04),
    0 10px 28px rgba(15, 23, 42, 0.08);
  margin: 0 !important;
  box-sizing: border-box !important;
  position: relative !important;
  overflow: hidden !important;
}
.docx table {
  border-collapse: collapse;
}
.docx table td,
.docx table th {
  vertical-align: top;
}
.docx img {
  max-width: 100%;
}
.docx p {
  margin: 0pt;
  min-height: 1em;
}
`;

/**
 * Browsers only natively preview images and PDF.
 * DOCX → docx-preview in isolated iframe; XLSX/XLS → styled HTML table.
 * Markdown / HTML → rendered preview (+ plain edit); other text → UTF-8 body.
 */
export function detectPreviewKind(file) {
    if (!file) return 'none';
    if (file.preview_kind === 'google_doc' || file.is_google_doc) return 'google_doc';
    if (file.preview_kind === 'google_sheet' || file.is_google_sheet) return 'google_sheet';
    if (file.preview_kind === 'markdown') return 'markdown';
    if (file.preview_kind === 'html') return 'html';

    const name = (file.original_name || '').toLowerCase();
    const mime = (file.mime_type || '').toLowerCase();
    const ext = name.includes('.') ? name.split('.').pop() : '';

    if (file.is_image) return 'image';
    if (file.is_pdf || mime === 'application/pdf' || name.endsWith('.pdf')) return 'pdf';
    if (name.endsWith('.docx') || mime.includes('wordprocessingml.document')) return 'docx';
    if (
        name.endsWith('.xlsx')
        || name.endsWith('.xls')
        || mime.includes('spreadsheetml')
        || mime.includes('ms-excel')
        || mime.includes('spreadsheet')
    ) return 'xlsx';
    if (name.endsWith('.doc')) return 'doc-legacy';
    if (name.endsWith('.ppt') || name.endsWith('.pptx')) return 'ppt-legacy';
    if (ext === 'md' || mime === 'text/markdown' || mime === 'text/x-markdown') return 'markdown';
    if (ext === 'html' || ext === 'htm' || mime === 'text/html') return 'html';
    if (file.preview_kind === 'text' || file.can_edit_content) return 'text';
    if (TEXT_EXTS.includes(ext) || mime.startsWith('text/') || mime === 'application/json') return 'text';

    return 'none';
}

export const TEXT_PREVIEW_KINDS = ['text', 'markdown', 'html'];
const OFFICE_PREVIEW_KINDS = ['docx', 'xlsx', 'doc-legacy', 'ppt-legacy'];

function parseCssLengthPx(value) {
    if (value == null || value === '') return 0;
    if (typeof value === 'number' && Number.isFinite(value)) return value;
    const raw = String(value).trim();
    const num = parseFloat(raw);
    if (!Number.isFinite(num)) return 0;
    if (raw.endsWith('pt')) return num * (96 / 72);
    if (raw.endsWith('in')) return num * 96;
    if (raw.endsWith('cm')) return num * (96 / 2.54);
    if (raw.endsWith('mm')) return num * (96 / 25.4);
    return num;
}

/** Skeleton của iframe — nạp qua srcdoc để tránh đua với `about:blank`. */
const DOCX_IFRAME_SRCDOC = `<!DOCTYPE html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>${DOCX_IFRAME_BASE_STYLES}</style></head>
<body><div class="docx-fit-host"><div class="docx-fit-inner"></div></div></body></html>`;

export function useDocumentPreview(selectedFileRef) {
    const kind = ref('none');
    const loading = ref(false);
    const error = ref('');
    const textContent = ref('');
    const xlsxHtml = ref('');
    const xlsxSheetNames = ref([]);
    const activeSheet = ref('');
    const docxIframe = ref(null);
    const xlsxWorkbook = ref(null);
    const xlsxPage = ref(1);
    const xlsxTotalRows = ref(0);
    const xlsxPageSize = XLSX_PAGE_SIZE;
    const zoom = ref(1);
    const autoFit = ref(true);

    let abort = null;
    let docxResizeObserver = null;
    let docxFitRaf = 0;
    /**
     * Buffer được GIỮ LẠI cho tới khi unmount — nếu iframe bị trình duyệt
     * re-navigate (mount trong modal `v-if`), render lại được thay vì trắng trang.
     */
    let pendingDocxBuffer = null;
    let docxRenderToken = 0;

    const xlsxPageCount = computed(() => {
        const total = xlsxTotalRows.value;
        if (total <= 0) return 0;
        return Math.max(1, Math.ceil(total / xlsxPageSize));
    });

    const xlsxRowRange = computed(() => {
        const total = xlsxTotalRows.value;
        if (total <= 0) return { from: 0, to: 0, total: 0 };
        const from = (xlsxPage.value - 1) * xlsxPageSize + 1;
        const to = Math.min(xlsxPage.value * xlsxPageSize, total);
        return { from, to, total };
    });

    const xlsxRowLabel = computed(() => {
        const { from, to, total } = xlsxRowRange.value;
        if (total === 0) return 'Không có dòng dữ liệu';
        return `Dòng ${from}–${to} / ${total}`;
    });

    const canZoom = computed(() => kind.value === 'docx');

    const detachDocxResizeObserver = () => {
        if (docxResizeObserver) {
            docxResizeObserver.disconnect();
            docxResizeObserver = null;
        }
        if (docxFitRaf) {
            cancelAnimationFrame(docxFitRaf);
            docxFitRaf = 0;
        }
    };

    const applyDocxFit = () => {
        const iframe = docxIframe.value;
        const doc = iframe?.contentDocument;
        const host = doc?.querySelector('.docx-fit-host');
        const inner = doc?.querySelector('.docx-fit-inner');
        if (!iframe || !host || !inner) return;

        const section = inner.querySelector('section.docx');
        const pageWidth = parseCssLengthPx(section?.style?.width)
            || section?.offsetWidth
            || inner.scrollWidth
            || 794;

        let scale = zoom.value;
        if (autoFit.value) {
            const gutter = 56;
            const available = Math.max(160, (iframe.clientWidth || 0) - gutter);
            scale = Math.min(1, available / pageWidth);
            if (!Number.isFinite(scale) || scale <= 0) scale = 1;
            scale = Math.max(0.4, Math.min(1, scale));
            zoom.value = scale;
        }

        // Reset trước khi đo chiều cao thật
        inner.style.transform = 'none';
        host.style.width = 'auto';
        host.style.height = 'auto';

        const naturalWidth = Math.max(pageWidth, inner.scrollWidth || 0);
        const naturalHeight = inner.scrollHeight || 0;

        inner.style.transformOrigin = 'top left';
        inner.style.transform = `scale(${scale})`;
        host.style.width = `${Math.ceil(naturalWidth * scale)}px`;
        host.style.height = `${Math.ceil(naturalHeight * scale)}px`;
        host.style.margin = '0 auto';
    };

    const scheduleDocxFit = () => {
        if (docxFitRaf) cancelAnimationFrame(docxFitRaf);
        docxFitRaf = requestAnimationFrame(() => {
            docxFitRaf = 0;
            applyDocxFit();
        });
    };

    const attachDocxResizeObserver = () => {
        detachDocxResizeObserver();
        const iframe = docxIframe.value;
        if (!iframe || typeof ResizeObserver === 'undefined') return;
        docxResizeObserver = new ResizeObserver(() => {
            if (autoFit.value) scheduleDocxFit();
        });
        docxResizeObserver.observe(iframe);
    };

    const waitForDocxImages = (doc) => {
        const images = Array.from(doc.querySelectorAll('section.docx img'));
        if (!images.length) return Promise.resolve();

        return Promise.all(images.map((img) => {
            if (img.complete && img.naturalWidth > 0) return Promise.resolve();
            return new Promise((resolve) => {
                const done = () => {
                    img.removeEventListener('load', done);
                    img.removeEventListener('error', done);
                    resolve();
                };
                img.addEventListener('load', done);
                img.addEventListener('error', done);
                setTimeout(done, 2500);
            });
        }));
    };

    /** Chờ iframe có document thật sự dùng được (srcdoc đã parse xong). */
    const waitForIframeDocument = (iframe) => new Promise((resolve) => {
        const ready = () => Boolean(iframe.contentDocument?.querySelector('.docx-fit-inner'));
        if (ready()) {
            resolve(true);
            return;
        }

        let settled = false;
        const finish = (ok) => {
            if (settled) return;
            settled = true;
            iframe.removeEventListener('load', onLoad);
            clearInterval(poll);
            clearTimeout(bail);
            resolve(ok);
        };

        const onLoad = () => {
            if (ready()) finish(true);
        };
        iframe.addEventListener('load', onLoad);
        // srcdoc đôi khi hoàn tất trước khi listener kịp gắn → poll ngắn để bù.
        const poll = setInterval(() => {
            if (ready()) finish(true);
        }, 50);
        const bail = setTimeout(() => finish(ready()), 4000);
    });

    const renderDocx = async () => {
        const iframe = docxIframe.value;
        const buffer = pendingDocxBuffer;
        if (!iframe || !buffer) return;

        const token = ++docxRenderToken;

        const ok = await waitForIframeDocument(iframe);
        // File khác đã được chọn trong lúc chờ → bỏ kết quả cũ.
        if (token !== docxRenderToken) return;
        if (!ok) {
            error.value = 'Không khởi tạo được khung xem Word.';
            return;
        }

        const doc = iframe.contentDocument;
        const inner = doc?.querySelector('.docx-fit-inner');
        if (!inner) return;

        inner.innerHTML = '';

        try {
            // renderAsync tiêu thụ ArrayBuffer, nên đưa bản sao để buffer gốc
            // còn dùng lại được nếu iframe bị dựng lại.
            await renderAsync(buffer.slice(0), inner, doc.head, DOCX_RENDER_OPTIONS);
        } catch (e) {
            if (token !== docxRenderToken) return;
            console.error(e);
            error.value = 'Không render được nội dung file Word.';
            return;
        }

        if (token !== docxRenderToken) return;

        attachDocxResizeObserver();
        scheduleDocxFit();
        await waitForDocxImages(doc);
        if (token !== docxRenderToken) return;
        scheduleDocxFit();
        setTimeout(scheduleDocxFit, 120);
    };

    const refreshXlsxHtml = () => {
        const workbook = xlsxWorkbook.value;
        const sheet = workbook?.Sheets?.[activeSheet.value];
        if (!sheet || !sheet['!ref']) {
            xlsxHtml.value = '';
            xlsxTotalRows.value = 0;
            return;
        }

        const range = XLSX.utils.decode_range(sheet['!ref']);
        const total = range.e.r - range.s.r + 1;
        xlsxTotalRows.value = total;

        const maxPage = Math.max(1, Math.ceil(total / xlsxPageSize));
        if (xlsxPage.value > maxPage) xlsxPage.value = maxPage;
        if (xlsxPage.value < 1) xlsxPage.value = 1;

        const startRow = range.s.r + (xlsxPage.value - 1) * xlsxPageSize;
        const endRow = Math.min(range.e.r, startRow + xlsxPageSize - 1);

        const { html } = renderSheetToHtml(XLSX, sheet, { startRow, endRow });
        xlsxHtml.value = html;
    };

    const switchSheet = (sheetName) => {
        if (!xlsxWorkbook.value || kind.value !== 'xlsx') return;
        activeSheet.value = sheetName;
        xlsxPage.value = 1;
        refreshXlsxHtml();
        error.value = xlsxHtml.value ? '' : 'Sheet này không có dữ liệu.';
    };

    const goToXlsxPage = (page) => {
        if (kind.value !== 'xlsx') return;
        const maxPage = xlsxPageCount.value || 1;
        xlsxPage.value = Math.min(Math.max(1, Number(page) || 1), maxPage);
        refreshXlsxHtml();
    };

    const nextXlsxPage = () => goToXlsxPage(xlsxPage.value + 1);
    const prevXlsxPage = () => goToXlsxPage(xlsxPage.value - 1);

    const setZoom = (value) => {
        const next = Math.max(0.4, Math.min(2, Number(value) || 1));
        autoFit.value = false;
        zoom.value = next;
        scheduleDocxFit();
    };

    const zoomIn = () => {
        const next = ZOOM_STEPS.find((s) => s > zoom.value + 0.001);
        setZoom(next ?? ZOOM_STEPS[ZOOM_STEPS.length - 1]);
    };

    const zoomOut = () => {
        const lower = [...ZOOM_STEPS].reverse().find((s) => s < zoom.value - 0.001);
        setZoom(lower ?? ZOOM_STEPS[0]);
    };

    const resetZoom = () => {
        autoFit.value = true;
        scheduleDocxFit();
    };

    const reset = () => {
        loading.value = false;
        error.value = '';
        textContent.value = '';
        xlsxHtml.value = '';
        xlsxSheetNames.value = [];
        activeSheet.value = '';
        xlsxWorkbook.value = null;
        xlsxPage.value = 1;
        xlsxTotalRows.value = 0;
        kind.value = 'none';
        zoom.value = 1;
        autoFit.value = true;
        pendingDocxBuffer = null;
        docxRenderToken += 1;
        detachDocxResizeObserver();
    };

    const loadText = async (file, previewKind = 'text') => {
        kind.value = TEXT_PREVIEW_KINDS.includes(previewKind) ? previewKind : 'text';
        if (file.size > MAX_TEXT_BYTES) {
            error.value = 'File quá lớn để xem/sửa trên trình duyệt (>1MB).';
            return;
        }

        loading.value = true;
        error.value = '';
        const controller = new AbortController();
        abort = controller;

        try {
            const bust = `${file.url}${file.url.includes('?') ? '&' : '?'}_=${Date.now()}`;
            const res = await fetch(bust, { signal: controller.signal, credentials: 'same-origin' });
            if (!res.ok) throw new Error(`Không tải được file (${res.status})`);
            textContent.value = await res.text();
            loading.value = false;
        } catch (e) {
            if (e.name === 'AbortError') return;
            error.value = e.message || 'Không thể đọc nội dung file.';
            loading.value = false;
        }
    };

    const loadOffice = async (file) => {
        const previewKind = detectPreviewKind(file);
        kind.value = previewKind;

        if (!OFFICE_PREVIEW_KINDS.includes(previewKind)) return;

        if (previewKind === 'doc-legacy') {
            error.value = 'File .doc (Word 97-2003) không xem trước được trên trình duyệt. '
                + 'Vui lòng tải xuống, hoặc lưu lại dưới dạng .docx rồi tải lên để xem trực tiếp.';
            return;
        }

        if (previewKind === 'ppt-legacy') {
            error.value = 'File PowerPoint chưa hỗ trợ xem trước. Vui lòng tải xuống để mở.';
            return;
        }

        if (file.size > MAX_OFFICE_BYTES) {
            error.value = 'File quá lớn để xem trước trên trình duyệt (>15MB). Vui lòng tải xuống.';
            return;
        }

        loading.value = true;
        error.value = '';
        xlsxHtml.value = '';

        const controller = new AbortController();
        abort = controller;

        try {
            const res = await fetch(file.url, { signal: controller.signal, credentials: 'same-origin' });
            if (!res.ok) throw new Error(`Không tải được file (${res.status})`);
            const buffer = await res.arrayBuffer();

            if (previewKind === 'docx') {
                pendingDocxBuffer = buffer;
                loading.value = false;
                await renderDocx();
            } else {
                // `cellStyles` bắt buộc — thiếu nó thì màu/border/độ rộng cột
                // bị loại bỏ ngay từ khâu đọc và không thể khôi phục.
                const workbook = XLSX.read(buffer, {
                    type: 'array',
                    cellStyles: true,
                    cellDates: true,
                    cellNF: true,
                });
                xlsxWorkbook.value = workbook;
                xlsxSheetNames.value = workbook.SheetNames ?? [];
                activeSheet.value = workbook.SheetNames?.[0] ?? '';
                xlsxPage.value = 1;
                if (activeSheet.value) refreshXlsxHtml();
                if (!xlsxHtml.value) error.value = 'File Excel không có dữ liệu.';
                loading.value = false;
            }
        } catch (e) {
            if (e.name === 'AbortError') return;
            console.error(e);
            error.value = e.message || 'Không thể xem trước file này.';
            loading.value = false;
        }
    };

    const setDocxIframe = (el) => {
        docxIframe.value = el;
        // Iframe vừa (re)mount mà buffer vẫn còn → render lại. Đây là lý do
        // buffer không bị xoá sau lần render đầu.
        if (el && pendingDocxBuffer) {
            renderDocx().catch((e) => {
                console.error(e);
                error.value = 'Không render được file Word.';
            });
        }
    };

    const reload = () => {
        const file = selectedFileRef.value;
        if (abort) abort.abort();
        reset();
        if (!file) return;
        if (!file.url && !file.is_external_link) {
            error.value = 'File không còn trên máy chủ.';
            return;
        }
        if (!file.url && file.is_external_link) {
            kind.value = detectPreviewKind(file);
            return;
        }
        kind.value = detectPreviewKind(file);
        if (TEXT_PREVIEW_KINDS.includes(kind.value)) {
            loadText(file, kind.value);
        } else if (OFFICE_PREVIEW_KINDS.includes(kind.value)) {
            loadOffice(file);
        }
    };

    watch(selectedFileRef, () => {
        reload();
    }, { immediate: true });

    onBeforeUnmount(() => {
        if (abort) abort.abort();
        pendingDocxBuffer = null;
        detachDocxResizeObserver();
    });

    return {
        kind,
        loading,
        error,
        textContent,
        xlsxHtml,
        xlsxSheetNames,
        activeSheet,
        setDocxIframe,
        switchSheet,
        reload,
        detectPreviewKind,
        xlsxPage,
        xlsxPageCount,
        xlsxRowRange,
        xlsxRowLabel,
        xlsxPageSize,
        goToXlsxPage,
        nextXlsxPage,
        prevXlsxPage,
        docxSrcdoc: DOCX_IFRAME_SRCDOC,
        zoom,
        autoFit,
        canZoom,
        zoomIn,
        zoomOut,
        setZoom,
        resetZoom,
    };
}
