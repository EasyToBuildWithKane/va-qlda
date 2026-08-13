import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue';
import { renderAsync } from 'docx-preview';
import * as XLSX from 'xlsx-js-style';

const MAX_OFFICE_BYTES = 15 * 1024 * 1024; // 15 MB
const MAX_TEXT_BYTES = 1 * 1024 * 1024; // 1 MB
const XLSX_PAGE_SIZE = 100;
const TEXT_EXTS = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'yml', 'yaml', 'html', 'htm', 'css', 'js', 'ts', 'vue', 'php', 'ini', 'env'];

/** className phải là `docx` — khớp selector `section.docx` của thư viện + CSS inject. */
const DOCX_RENDER_OPTIONS = {
    className: 'docx',
    inWrapper: true,
    ignoreWidth: false,
    ignoreHeight: false,
    ignoreFonts: false,
    breakPages: true,
    ignoreLastRenderedPageBreak: true,
    experimental: false,
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
 * Canvas trong iframe — cuộn liên tục như Google Docs.
 * Không bọc/scale từng trang (tránh lệch header / footer / watermark).
 * Fit chiều ngang bằng transform cả khối `.docx-fit-inner`.
 */
const DOCX_IFRAME_BASE_STYLES = `
html, body {
  margin: 0;
  padding: 0;
  background: #e8edf3;
  color: #0f172a;
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
  padding: 20px 12px 28px !important;
  box-sizing: border-box;
  display: flex !important;
  flex-flow: column nowrap !important;
  align-items: center !important;
  gap: 0;
  width: max-content;
  max-width: none;
}
.docx-wrapper > section.docx {
  background: #fff !important;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.1), 0 10px 28px rgba(15, 23, 42, 0.08);
  margin: 0 0 20px !important;
  box-sizing: border-box !important;
  /* Giữ flex thư viện — header / article / footer / watermark đúng lớp */
  display: flex !important;
  flex-flow: column nowrap !important;
  position: relative !important;
  overflow: hidden !important;
}
.docx-wrapper > section.docx:last-child {
  margin-bottom: 0 !important;
}
section.docx > article {
  margin-bottom: auto;
  z-index: 1;
  position: relative;
}
section.docx > header,
section.docx > footer {
  z-index: 1;
  position: relative;
}
.docx table {
  border-collapse: collapse;
}
.docx img {
  max-width: 100%;
  height: auto;
}
.docx p {
  margin: 0pt;
  min-height: 1em;
}
`;

/**
 * Browsers only natively preview images and PDF.
 * DOCX → docx-preview in isolated iframe; XLSX/XLS → paginated HTML table.
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
    if (ext === 'md' || mime === 'text/markdown' || mime === 'text/x-markdown') return 'markdown';
    if (ext === 'html' || ext === 'htm' || mime === 'text/html') return 'html';
    if (file.preview_kind === 'text' || file.can_edit_content) return 'text';
    if (TEXT_EXTS.includes(ext) || mime.startsWith('text/') || mime === 'application/json') return 'text';

    return 'none';
}

export const TEXT_PREVIEW_KINDS = ['text', 'markdown', 'html'];

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatCellValue(value) {
    if (value == null || value === '') return '';
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
        return value.toLocaleString('vi-VN');
    }
    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
    }
    return String(value);
}

function ensureDocxIframeDocument(iframe) {
    const doc = iframe.contentDocument;
    if (!doc) return null;

    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div class="docx-fit-host"><div class="docx-fit-inner"></div></div></body></html>');
    doc.close();

    const style = doc.createElement('style');
    style.setAttribute('data-va-docx-preview', '1');
    style.textContent = DOCX_IFRAME_BASE_STYLES;
    doc.head.appendChild(style);

    return doc;
}

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

export function useDocumentPreview(selectedFileRef) {
    const kind = ref('none');
    const loading = ref(false);
    const error = ref('');
    const textContent = ref('');
    const xlsxHtml = ref('');
    const xlsxSheetNames = ref([]);
    const activeSheet = ref('');
    const docxIframe = ref(null);
    const docxBuffer = ref(null);
    const xlsxWorkbook = ref(null);
    const xlsxRows = ref([]);
    const xlsxPage = ref(1);
    const xlsxPageSize = XLSX_PAGE_SIZE;

    let abort = null;
    let docxResizeObserver = null;
    let docxFitRaf = 0;

    const xlsxPageCount = computed(() => {
        const total = xlsxRows.value.length;
        if (total === 0) return 0;
        return Math.max(1, Math.ceil(Math.max(0, total - 1) / xlsxPageSize) || 1);
    });

    const xlsxRowRange = computed(() => {
        const totalData = Math.max(0, xlsxRows.value.length - 1);
        if (totalData === 0) {
            return { from: 0, to: 0, total: 0 };
        }
        const from = (xlsxPage.value - 1) * xlsxPageSize + 1;
        const to = Math.min(xlsxPage.value * xlsxPageSize, totalData);
        return { from, to, total: totalData };
    });

    const xlsxRowLabel = computed(() => {
        const { from, to, total } = xlsxRowRange.value;
        if (total === 0) return 'Không có dòng dữ liệu';
        return `Dòng ${from}–${to} / ${total}`;
    });

    const clearDocxIframe = () => {
        const iframe = docxIframe.value;
        if (!iframe?.contentDocument?.body) return;
        iframe.contentDocument.body.innerHTML = '<div class="docx-fit-host"><div class="docx-fit-inner"></div></div>';
    };

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

        const gutter = 32;
        const available = Math.max(160, (iframe.clientWidth || 0) - gutter);
        let scale = Math.min(1, available / pageWidth);
        if (!Number.isFinite(scale) || scale <= 0) scale = 1;
        scale = Math.max(0.4, Math.min(1, scale));

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
        docxResizeObserver = new ResizeObserver(() => scheduleDocxFit());
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

    const reset = () => {
        loading.value = false;
        error.value = '';
        textContent.value = '';
        xlsxHtml.value = '';
        xlsxSheetNames.value = [];
        activeSheet.value = '';
        docxBuffer.value = null;
        xlsxWorkbook.value = null;
        xlsxRows.value = [];
        xlsxPage.value = 1;
        kind.value = 'none';
        detachDocxResizeObserver();
        clearDocxIframe();
    };

    const renderDocx = async () => {
        const iframe = docxIframe.value;
        const buffer = docxBuffer.value;
        if (!iframe || !buffer) return;

        // Claim buffer immediately so setDocxIframe + loadOffice cannot double-render
        docxBuffer.value = null;

        const doc = ensureDocxIframeDocument(iframe);
        if (!doc) {
            docxBuffer.value = buffer;
            return;
        }

        const inner = doc.querySelector('.docx-fit-inner');
        if (!inner) {
            docxBuffer.value = buffer;
            return;
        }
        inner.innerHTML = '';

        await renderAsync(buffer, inner, doc.head, DOCX_RENDER_OPTIONS);

        // Không wrap từng trang — để thư viện giữ header/footer/watermark
        attachDocxResizeObserver();
        await waitForDocxImages(doc);
        scheduleDocxFit();
        setTimeout(scheduleDocxFit, 50);
        setTimeout(scheduleDocxFit, 250);
    };

    const buildXlsxTableHtml = (rows, page) => {
        if (!rows.length) return '';

        const header = rows[0] || [];
        const dataRows = rows.slice(1);
        const totalData = dataRows.length;
        const start = (page - 1) * xlsxPageSize;
        const slice = dataRows.slice(start, start + xlsxPageSize);
        const colCount = Math.max(header.length, ...slice.map((r) => r.length), 1);

        const headCells = [];
        for (let c = 0; c < colCount; c += 1) {
            const label = formatCellValue(header[c]);
            headCells.push(
                `<th title="${escapeHtml(label)}"><span>${escapeHtml(label || `Cột ${c + 1}`)}</span></th>`,
            );
        }

        const bodyRows = slice.map((row, rowIndex) => {
            const cells = [];
            for (let c = 0; c < colCount; c += 1) {
                const label = formatCellValue(row[c]);
                cells.push(
                    `<td title="${escapeHtml(label)}"><span>${escapeHtml(label)}</span></td>`,
                );
            }
            const zebra = rowIndex % 2 === 1 ? ' class="xlsx-row-alt"' : '';
            return `<tr${zebra}>${cells.join('')}</tr>`;
        });

        if (!bodyRows.length && totalData === 0) {
            return `
              <table class="xlsx-preview-table">
                <thead><tr>${headCells.join('')}</tr></thead>
                <tbody><tr><td colspan="${colCount}">Sheet trống</td></tr></tbody>
              </table>
            `;
        }

        return `
          <table class="xlsx-preview-table">
            <thead><tr>${headCells.join('')}</tr></thead>
            <tbody>${bodyRows.join('')}</tbody>
          </table>
        `;
    };

    const refreshXlsxHtml = () => {
        if (!xlsxRows.value.length) {
            xlsxHtml.value = '';
            return;
        }
        const maxPage = Math.max(1, Math.ceil(Math.max(0, xlsxRows.value.length - 1) / xlsxPageSize) || 1);
        if (xlsxPage.value > maxPage) xlsxPage.value = maxPage;
        if (xlsxPage.value < 1) xlsxPage.value = 1;
        xlsxHtml.value = buildXlsxTableHtml(xlsxRows.value, xlsxPage.value);
    };

    const loadSheetRows = (workbook, sheetName) => {
        const sheet = workbook.Sheets[sheetName];
        if (!sheet) {
            xlsxRows.value = [];
            return;
        }
        const rows = XLSX.utils.sheet_to_json(sheet, {
            header: 1,
            defval: '',
            raw: false,
            blankrows: false,
            dateNF: 'dd/mm/yyyy',
        });
        xlsxRows.value = Array.isArray(rows) ? rows : [];
    };

    const switchSheet = (sheetName) => {
        if (!xlsxWorkbook.value || kind.value !== 'xlsx') return;
        activeSheet.value = sheetName;
        xlsxPage.value = 1;
        loadSheetRows(xlsxWorkbook.value, sheetName);
        refreshXlsxHtml();
        if (!xlsxHtml.value) error.value = 'File Excel không có dữ liệu.';
        else error.value = '';
    };

    const goToXlsxPage = (page) => {
        if (kind.value !== 'xlsx') return;
        const maxPage = xlsxPageCount.value || 1;
        xlsxPage.value = Math.min(Math.max(1, Number(page) || 1), maxPage);
        refreshXlsxHtml();
    };

    const nextXlsxPage = () => goToXlsxPage(xlsxPage.value + 1);
    const prevXlsxPage = () => goToXlsxPage(xlsxPage.value - 1);

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

        if (!['docx', 'xlsx', 'doc-legacy'].includes(previewKind)) {
            return;
        }

        if (previewKind === 'doc-legacy') {
            error.value = 'File .doc (Word cũ) chưa hỗ trợ xem trước. Vui lòng tải xuống hoặc chuyển sang .docx.';
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
                docxBuffer.value = buffer;
                loading.value = false;
                await nextTick();
                await renderDocx();
            } else {
                const workbook = XLSX.read(buffer, { type: 'array', cellDates: true });
                xlsxWorkbook.value = workbook;
                xlsxSheetNames.value = workbook.SheetNames;
                activeSheet.value = workbook.SheetNames[0] ?? '';
                xlsxPage.value = 1;
                if (activeSheet.value) {
                    loadSheetRows(workbook, activeSheet.value);
                    refreshXlsxHtml();
                }
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
        if (el && docxBuffer.value) {
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
        } else if (['docx', 'xlsx', 'doc-legacy'].includes(kind.value)) {
            loadOffice(file);
        }
    };

    watch(selectedFileRef, () => {
        reload();
    }, { immediate: true });

    onBeforeUnmount(() => {
        if (abort) abort.abort();
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
    };
}
