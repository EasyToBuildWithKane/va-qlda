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
    // true = mặc định thư viện; false dễ cắt header/floating thành «mảnh» trắng trên nền xám
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
 * CSS bổ sung trong iframe (không Tailwind).
 * Giữ display:flex + overflow:hidden của section.docx như thư viện.
 * Sau khi wrap slot/scale, selector `.docx-wrapper>section.docx` không còn khớp
 * → phải gắn background trắng trực tiếp lên section.
 */
const DOCX_IFRAME_BASE_STYLES = `
html, body {
  margin: 0;
  padding: 0;
  background: #f1f5f9;
  color: #0f172a;
}
body {
  min-height: 100%;
  box-sizing: border-box;
  padding: 12px 8px 20px;
}
.docx-shell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
  min-height: 100%;
  width: 100%;
}
/* Ghi đè wrapper xám mặc định của docx-preview */
.docx-wrapper {
  background: transparent !important;
  padding: 0 !important;
  display: flex !important;
  flex-flow: column nowrap !important;
  align-items: center !important;
  gap: 0;
  width: 100%;
  box-sizing: border-box;
}
/* Slot = đúng kích thước sau scale; clip layout chưa scale của .docx-page-scale */
.docx-page-slot {
  display: block;
  box-sizing: border-box;
  margin: 0 auto 16px;
  overflow: hidden;
  flex-shrink: 0;
}
.docx-page-slot.docx-page-hidden {
  display: none !important;
}
.docx-page-scale {
  transform-origin: top left;
  will-change: transform;
}
section.docx {
  background: #fff !important;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12), 0 8px 24px rgba(15, 23, 42, 0.08);
  margin: 0 !important;
  box-sizing: border-box !important;
  display: flex !important;
  flex-flow: column nowrap !important;
  position: relative !important;
  /* Khớp thư viện — clip header/drawing theo khung trang (tránh mảnh trắng nổi) */
  overflow: hidden !important;
}
section.docx > article {
  margin-bottom: auto;
  z-index: 1;
  max-width: 100%;
  min-width: 0;
  overflow-x: auto;
  overflow-y: visible;
}
section.docx > header,
section.docx > footer {
  z-index: 1;
  max-width: 100%;
  position: relative;
}
/* Bảng trong thân trang: không phá letterhead */
section.docx > article table {
  border-collapse: collapse;
  max-width: 100%;
  table-layout: auto;
}
section.docx > article table td,
section.docx > article table th {
  vertical-align: top;
  overflow-wrap: anywhere;
  word-break: break-word;
  hyphens: auto;
}
section.docx > article table p {
  margin: 0;
  min-height: 1em;
}
/* Chỉ co ảnh trong article — không đụng logo/header/footer */
section.docx > article img {
  max-width: 100% !important;
  height: auto !important;
  object-fit: contain;
}
section.docx > article svg {
  max-width: 100%;
  height: auto;
}
.docx p {
  margin: 0pt;
  min-height: 1em;
}
.docx span {
  white-space: pre-wrap;
  overflow-wrap: break-word;
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
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><div class="docx-shell"></div></body></html>');
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
    const pageCount = ref(0);
    const currentPage = ref(1);
    /** 'fit' | '100' | '75' */
    const docxZoom = ref('fit');
    /** 'single' | 'continuous' */
    const docxViewMode = ref('single');

    let abort = null;
    let docxPageSlots = [];
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
        iframe.contentDocument.body.innerHTML = '<div class="docx-shell"></div>';
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

    const applyDocxScale = () => {
        const iframe = docxIframe.value;
        const doc = iframe?.contentDocument;
        if (!doc || !docxPageSlots.length) return;

        const gutter = 24;
        const available = Math.max(120, (iframe.clientWidth || 0) - gutter);

        docxPageSlots.forEach((slot) => {
            const scaleEl = slot.querySelector('.docx-page-scale');
            const section = scaleEl?.querySelector('section.docx') || slot.querySelector('section.docx');
            if (!scaleEl || !section) return;

            // Ưu tiên kích thước trang khai báo (width/minHeight) — ổn định hơn offset sau reflow ảnh
            const pageWidth = parseCssLengthPx(section.style.width)
                || section.offsetWidth
                || section.scrollWidth
                || 794;
            const pageHeight = parseCssLengthPx(section.style.minHeight)
                || section.offsetHeight
                || section.scrollHeight
                || 1123;

            let scale = 1;
            if (docxZoom.value === 'fit') {
                scale = Math.min(1, available / pageWidth);
            } else if (docxZoom.value === '75') {
                scale = 0.75;
            } else {
                scale = 1;
            }

            if (!Number.isFinite(scale) || scale <= 0) scale = 1;
            scale = Math.max(0.35, Math.min(1.25, scale));

            // top-left + slot đúng size sau scale — tránh clip ngang khi flex-center trang full-width trong khung hẹp
            scaleEl.style.width = `${pageWidth}px`;
            scaleEl.style.height = `${pageHeight}px`;
            scaleEl.style.transformOrigin = 'top left';
            scaleEl.style.transform = `scale(${scale})`;

            slot.style.width = `${Math.ceil(pageWidth * scale)}px`;
            slot.style.height = `${Math.ceil(pageHeight * scale)}px`;
            slot.style.overflow = 'hidden';
        });
    };

    const scheduleDocxScale = () => {
        if (docxFitRaf) cancelAnimationFrame(docxFitRaf);
        docxFitRaf = requestAnimationFrame(() => {
            docxFitRaf = 0;
            applyDocxScale();
        });
    };

    const wrapDocxPages = (doc, sections) => {
        const slots = [];
        sections.forEach((section) => {
            const parent = section.parentElement;
            if (!parent) return;

            const slot = doc.createElement('div');
            slot.className = 'docx-page-slot';
            const scale = doc.createElement('div');
            scale.className = 'docx-page-scale';

            parent.insertBefore(slot, section);
            scale.appendChild(section);
            slot.appendChild(scale);
            slots.push(slot);
        });
        return slots;
    };

    const applyDocxPageVisibility = () => {
        if (!docxPageSlots.length) {
            pageCount.value = 0;
            return;
        }
        pageCount.value = docxPageSlots.length;
        if (currentPage.value < 1) currentPage.value = 1;
        if (currentPage.value > pageCount.value) currentPage.value = pageCount.value;

        const continuous = docxViewMode.value === 'continuous';
        docxPageSlots.forEach((slot, index) => {
            const visible = continuous || index + 1 === currentPage.value;
            slot.classList.toggle('docx-page-hidden', !visible);
        });

        scheduleDocxScale();

        const iframe = docxIframe.value;
        if (iframe?.contentWindow && !continuous) {
            iframe.contentWindow.scrollTo(0, 0);
        }
    };

    const goToPage = (page) => {
        if (kind.value !== 'docx' || pageCount.value < 1) return;
        if (docxViewMode.value === 'continuous') {
            const next = Math.min(Math.max(1, Number(page) || 1), pageCount.value);
            currentPage.value = next;
            const slot = docxPageSlots[next - 1];
            slot?.scrollIntoView({ block: 'start', behavior: 'smooth' });
            return;
        }
        const next = Math.min(Math.max(1, Number(page) || 1), pageCount.value);
        currentPage.value = next;
        applyDocxPageVisibility();
    };

    const nextPage = () => goToPage(currentPage.value + 1);
    const prevPage = () => goToPage(currentPage.value - 1);

    const setDocxZoom = (mode) => {
        if (!['fit', '100', '75'].includes(mode)) return;
        docxZoom.value = mode;
        scheduleDocxScale();
    };

    const setDocxViewMode = (mode) => {
        if (!['single', 'continuous'].includes(mode)) return;
        docxViewMode.value = mode;
        applyDocxPageVisibility();
    };

    const attachDocxResizeObserver = () => {
        detachDocxResizeObserver();
        const iframe = docxIframe.value;
        if (!iframe || typeof ResizeObserver === 'undefined') return;
        docxResizeObserver = new ResizeObserver(() => scheduleDocxScale());
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
        pageCount.value = 0;
        currentPage.value = 1;
        docxPageSlots = [];
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

        const shell = doc.querySelector('.docx-shell') || doc.body;
        shell.innerHTML = '';

        await renderAsync(buffer, shell, doc.head, DOCX_RENDER_OPTIONS);

        let sections = Array.from(doc.querySelectorAll('section.docx'));
        if (!sections.length) {
            const wrapper = shell.querySelector('.docx-wrapper') || shell;
            const fallback = doc.createElement('section');
            fallback.className = 'docx';
            while (wrapper.firstChild) {
                fallback.appendChild(wrapper.firstChild);
            }
            wrapper.appendChild(fallback);
            sections = [fallback];
        }

        docxPageSlots = wrapDocxPages(doc, sections);
        // Củng cố nền trang sau wrap (selector thư viện `.docx-wrapper>section` không còn khớp)
        docxPageSlots.forEach((slot) => {
            const section = slot.querySelector('section.docx');
            if (!section) return;
            section.style.backgroundColor = '#ffffff';
            if (!section.style.minHeight) {
                section.style.minHeight = '1123px';
            }
            if (!section.style.width) {
                section.style.width = '794px';
            }
        });
        currentPage.value = 1;
        applyDocxPageVisibility();
        attachDocxResizeObserver();

        await waitForDocxImages(doc);
        scheduleDocxScale();
        // Scale lại sau layout ổn định
        setTimeout(scheduleDocxScale, 50);
        setTimeout(scheduleDocxScale, 250);
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
        pageCount,
        currentPage,
        goToPage,
        nextPage,
        prevPage,
        docxZoom,
        setDocxZoom,
        docxViewMode,
        setDocxViewMode,
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
