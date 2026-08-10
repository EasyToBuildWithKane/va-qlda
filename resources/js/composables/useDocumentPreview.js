import { ref, computed, watch, onBeforeUnmount, nextTick } from 'vue';
import { renderAsync } from 'docx-preview';
import * as XLSX from 'xlsx-js-style';

const MAX_OFFICE_BYTES = 15 * 1024 * 1024; // 15 MB
const MAX_TEXT_BYTES = 1 * 1024 * 1024; // 1 MB
const XLSX_PAGE_SIZE = 100;
const TEXT_EXTS = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'yml', 'yaml', 'html', 'htm', 'css', 'js', 'ts', 'vue', 'php', 'ini', 'env'];

const DOCX_RENDER_OPTIONS = {
    className: 'docx-preview-content',
    inWrapper: true,
    ignoreWidth: false,
    ignoreHeight: false,
    ignoreFonts: false,
    breakPages: true,
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
  padding: 16px 12px 24px;
}
.docx-shell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
  min-height: 100%;
}
.docx-preview-content {
  background: transparent !important;
}
section.docx {
  background: #fff !important;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12), 0 8px 24px rgba(15, 23, 42, 0.08);
  margin: 0 auto 16px !important;
  box-sizing: border-box;
}
section.docx.docx-page-hidden {
  display: none !important;
}
section.docx.docx-page-visible {
  display: block !important;
}
`;

/**
 * Browsers only natively preview images and PDF.
 * DOCX → docx-preview in isolated iframe; XLSX/XLS → paginated HTML table.
 * Text → plain UTF-8 body for view/edit.
 */
export function detectPreviewKind(file) {
    if (!file) return 'none';
    if (file.preview_kind === 'google_doc' || file.is_google_doc) return 'google_doc';
    if (file.preview_kind === 'google_sheet' || file.is_google_sheet) return 'google_sheet';
    if (file.preview_kind === 'text' || file.can_edit_content) return 'text';

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
    if (TEXT_EXTS.includes(ext) || mime.startsWith('text/') || mime === 'application/json') return 'text';

    return 'none';
}

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
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div class="docx-shell"></div></body></html>');
    doc.close();

    const style = doc.createElement('style');
    style.textContent = DOCX_IFRAME_BASE_STYLES;
    doc.head.appendChild(style);

    return doc;
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

    let abort = null;
    let docxPageSections = [];

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
        docxPageSections = [];
        kind.value = 'none';
        clearDocxIframe();
    };

    const applyDocxPageVisibility = () => {
        if (!docxPageSections.length) {
            pageCount.value = 0;
            return;
        }
        pageCount.value = docxPageSections.length;
        if (currentPage.value < 1) currentPage.value = 1;
        if (currentPage.value > pageCount.value) currentPage.value = pageCount.value;

        docxPageSections.forEach((section, index) => {
            const visible = index + 1 === currentPage.value;
            section.classList.toggle('docx-page-hidden', !visible);
            section.classList.toggle('docx-page-visible', visible);
        });

        const iframe = docxIframe.value;
        if (iframe?.contentWindow) {
            iframe.contentWindow.scrollTo(0, 0);
        }
    };

    const goToPage = (page) => {
        if (kind.value !== 'docx' || pageCount.value < 1) return;
        const next = Math.min(Math.max(1, Number(page) || 1), pageCount.value);
        currentPage.value = next;
        applyDocxPageVisibility();
    };

    const nextPage = () => goToPage(currentPage.value + 1);
    const prevPage = () => goToPage(currentPage.value - 1);

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

        docxPageSections = Array.from(doc.querySelectorAll('section.docx'));
        if (!docxPageSections.length) {
            // Fallback: treat whole wrapper as one page
            const wrapper = shell.querySelector('.docx-wrapper, .docx-preview-content') || shell;
            docxPageSections = [wrapper];
        }

        currentPage.value = 1;
        applyDocxPageVisibility();
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
        // Keep at least a header row placeholder when sheet has data in non-A1 areas
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

    const loadText = async (file) => {
        kind.value = 'text';
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
        if (kind.value === 'text') {
            loadText(file);
        } else if (['docx', 'xlsx', 'doc-legacy'].includes(kind.value)) {
            loadOffice(file);
        }
    };

    watch(selectedFileRef, () => {
        reload();
    }, { immediate: true });

    onBeforeUnmount(() => {
        if (abort) abort.abort();
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
