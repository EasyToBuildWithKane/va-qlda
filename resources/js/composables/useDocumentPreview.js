import { ref, watch, onBeforeUnmount, nextTick } from 'vue';
import { renderAsync } from 'docx-preview';
import * as XLSX from 'xlsx-js-style';

const MAX_OFFICE_BYTES = 15 * 1024 * 1024; // 15 MB
const MAX_TEXT_BYTES = 1 * 1024 * 1024; // 1 MB
const TEXT_EXTS = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'yml', 'yaml', 'html', 'htm', 'css', 'js', 'ts', 'vue', 'php', 'ini', 'env'];

/**
 * Browsers only natively preview images and PDF.
 * DOCX → docx-preview (client-side), XLSX/XLS → SheetJS HTML table.
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

export function useDocumentPreview(selectedFileRef) {
    const kind = ref('none');
    const loading = ref(false);
    const error = ref('');
    const textContent = ref('');
    const xlsxHtml = ref('');
    const xlsxSheetNames = ref([]);
    const activeSheet = ref('');
    const docxContainer = ref(null);
    const docxBuffer = ref(null);
    const xlsxWorkbook = ref(null);

    let abort = null;

    const reset = () => {
        loading.value = false;
        error.value = '';
        textContent.value = '';
        xlsxHtml.value = '';
        xlsxSheetNames.value = [];
        activeSheet.value = '';
        docxBuffer.value = null;
        xlsxWorkbook.value = null;
        kind.value = 'none';
        if (docxContainer.value) docxContainer.value.innerHTML = '';
    };

    const renderXlsxSheet = (workbook, sheetName) => {
        const sheet = workbook.Sheets[sheetName];
        if (!sheet) return '';
        return XLSX.utils.sheet_to_html(sheet, { editable: false, header: '' });
    };

    const renderDocx = async () => {
        if (!docxContainer.value || !docxBuffer.value) return;
        docxContainer.value.innerHTML = '';
        await renderAsync(docxBuffer.value, docxContainer.value, null, {
            className: 'docx-preview-content',
            inWrapper: true,
            ignoreWidth: false,
            ignoreHeight: false,
            breakPages: true,
        });
        docxBuffer.value = null;
    };

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
                xlsxHtml.value = activeSheet.value ? renderXlsxSheet(workbook, activeSheet.value) : '';
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

    const setDocxContainer = (el) => {
        docxContainer.value = el;
        if (el && docxBuffer.value) {
            renderDocx().catch((e) => {
                console.error(e);
                error.value = 'Không render được file Word.';
            });
        }
    };

    const switchSheet = (sheetName) => {
        if (!xlsxWorkbook.value || kind.value !== 'xlsx') return;
        activeSheet.value = sheetName;
        xlsxHtml.value = renderXlsxSheet(xlsxWorkbook.value, sheetName);
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
        setDocxContainer,
        switchSheet,
        reload,
        detectPreviewKind,
    };
}
