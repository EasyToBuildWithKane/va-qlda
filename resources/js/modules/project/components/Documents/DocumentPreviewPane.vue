<script setup>
/* eslint-disable vue/no-v-html -- xlsx sheet + markdown preview HTML */
import { computed, toRef, watch } from 'vue';
import MarkdownIt from 'markdown-it';
import AppIcon from '@/Components/AppIcon.vue';
import { useDocumentPreview } from '@/composables/useDocumentPreview';

const props = defineProps({
    file: { type: Object, default: null },
    editing: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['update:draft', 'update:editing']);

const fileRef = toRef(() => props.file);

const md = new MarkdownIt({
    html: false,
    linkify: true,
    breaks: true,
});

const {
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
    xlsxPage,
    xlsxPageCount,
    xlsxRowLabel,
    nextXlsxPage,
    prevXlsxPage,
    docxSrcdoc,
    zoom,
    autoFit,
    canZoom,
    zoomIn,
    zoomOut,
    resetZoom,
} = useDocumentPreview(fileRef);

const zoomPercent = computed(() => `${Math.round(zoom.value * 100)}%`);

/** Link tải xuống — ép `Content-Disposition: attachment` phía server. */
const downloadUrl = computed(() => {
    const raw = props.file?.url;
    if (!raw) return '';
    if (props.file?.is_external_link) return raw;
    return `${raw}${raw.includes('?') ? '&' : '?'}download=1`;
});

const canOpenNewTab = computed(() => Boolean(props.file?.url));

const showToolbar = computed(() =>
    Boolean(props.file) && !loading.value && !error.value && kind.value !== 'none',
);

const draft = computed({
    get: () => textContent.value,
    set: (value) => {
        textContent.value = value;
        emit('update:draft', value);
    },
});

watch(textContent, (value) => {
    emit('update:draft', value);
});

const showFallback = computed(() =>
    props.file && !loading.value && !error.value
    && kind.value === 'none',
);

const isTextLikeKind = computed(() =>
    kind.value === 'text' || kind.value === 'markdown' || kind.value === 'html',
);

const markdownHtml = computed(() => {
    const raw = textContent.value ?? '';
    if (!raw.trim()) return '';
    return md.render(raw);
});

/** Sandboxed HTML preview — no scripts; wrap fragment files with base styles. */
const htmlSrcdoc = computed(() => {
    const raw = String(textContent.value ?? '');
    const trimmed = raw.trim();
    if (!trimmed) {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body></body></html>';
    }
    const looksFullDoc = /<!DOCTYPE\s+html/i.test(trimmed) || /<html[\s>]/i.test(trimmed);
    if (looksFullDoc) return raw;
    return `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>
body{margin:16px;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#0f172a;line-height:1.55;font-size:14px}
img,video{max-width:100%;height:auto}
table{border-collapse:collapse;max-width:100%}
td,th{border:1px solid #e2e8f0;padding:6px 8px;vertical-align:top}
pre{overflow:auto;background:#f8fafc;padding:12px;border-radius:8px}
a{color:#9A0036}
</style></head><body>${raw}</body></html>`;
});

/** Fit = thu trọn một trang trong khung iframe, không cuộn nội bộ */
const pdfPreviewUrl = computed(() => {
    const raw = props.file?.embed_url || props.file?.url;
    if (!raw) return '';
    const base = raw.split('#')[0];
    return `${base}#view=Fit&navpanes=0`;
});

const isGoogleEmbed = computed(() =>
    kind.value === 'google_doc' || kind.value === 'google_sheet',
);

const googleEmbedUrl = computed(() => {
    const raw = props.file?.embed_url || props.file?.url;
    if (!raw) return '';
    try {
        const u = new URL(raw);
        if (!u.searchParams.has('rm')) {
            u.searchParams.set('rm', 'minimal');
        }
        return u.toString();
    } catch {
        return raw.includes('rm=') ? raw : `${raw}${raw.includes('?') ? '&' : '?'}rm=minimal`;
    }
});

const showXlsxPager = computed(() =>
    kind.value === 'xlsx' && !loading.value && !error.value && xlsxPageCount.value >= 1,
);

defineExpose({ reload, textContent, kind });
</script>

<template>
  <div class="flex h-full min-h-0 flex-1 flex-col">
    <div
      v-if="!file"
      class="flex min-h-0 flex-1 items-center justify-center text-slate-400"
    >
      <p class="text-sm">
        Chọn file để xem trước
      </p>
    </div>

    <div
      v-else
      class="flex min-h-0 flex-1 flex-col"
    >
      <div
        v-if="showToolbar"
        class="doc-preview-toolbar"
      >
        <div
          v-if="canZoom"
          class="doc-preview-toolbar__group"
        >
          <button
            type="button"
            class="doc-preview-toolbar__btn"
            title="Thu nhỏ"
            :disabled="zoom <= 0.4"
            @click="zoomOut"
          >
            <AppIcon
              name="minus"
              :size="14"
            />
          </button>
          <button
            type="button"
            class="doc-preview-toolbar__zoom tabular-nums"
            title="Vừa khung"
            @click="resetZoom"
          >
            {{ autoFit ? 'Vừa khung' : zoomPercent }}
          </button>
          <button
            type="button"
            class="doc-preview-toolbar__btn"
            title="Phóng to"
            :disabled="zoom >= 2"
            @click="zoomIn"
          >
            <AppIcon
              name="plus"
              :size="14"
            />
          </button>
        </div>

        <div class="doc-preview-toolbar__spacer" />

        <a
          v-if="canOpenNewTab"
          :href="file.url"
          target="_blank"
          rel="noopener"
          class="doc-preview-toolbar__btn"
          title="Mở tab mới"
        >
          <AppIcon
            name="external-link"
            :size="14"
          />
        </a>
        <a
          v-if="downloadUrl"
          :href="downloadUrl"
          class="doc-preview-toolbar__btn"
          title="Tải xuống"
        >
          <AppIcon
            name="download"
            :size="14"
          />
        </a>
      </div>

      <div
        v-if="loading"
        class="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 text-slate-500"
      >
        <AppIcon
          name="refresh"
          :size="22"
          class="animate-spin text-brand"
        />
        <p class="text-sm">
          Đang mở tài liệu…
        </p>
      </div>

      <div
        v-else-if="error"
        class="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 px-6 text-center"
      >
        <AppIcon
          name="info"
          :size="28"
          class="text-amber-500"
        />
        <p class="text-sm text-slate-600 dark:text-slate-300">
          {{ error }}
        </p>
        <a
          v-if="file.url"
          :href="file.url"
          target="_blank"
          class="text-sm font-medium text-brand hover:underline"
        >Tải xuống</a>
      </div>

      <div
        v-else-if="isTextLikeKind"
        class="flex min-h-0 flex-1 flex-col"
      >
        <textarea
          v-if="editing && canEdit"
          v-model="draft"
          class="min-h-0 flex-1 resize-none border-0 bg-slate-50 px-4 py-3 font-mono text-sm leading-relaxed text-slate-800 outline-none focus:ring-0 dark:bg-slate-950 dark:text-slate-100"
          spellcheck="false"
        />
        <div
          v-else-if="kind === 'markdown'"
          class="doc-preview-markdown min-h-0 flex-1 overflow-auto bg-white px-5 py-4 dark:bg-slate-900"
        >
          <div
            v-if="markdownHtml"
            class="prose prose-sm max-w-none text-slate-800 dark:prose-invert dark:text-slate-100"
            v-html="markdownHtml"
          />
          <p
            v-else
            class="text-sm text-slate-400"
          >
            File trống
          </p>
        </div>
        <iframe
          v-else-if="kind === 'html'"
          class="doc-preview-html__iframe min-h-0 flex-1"
          title="Xem trước HTML"
          sandbox=""
          :srcdoc="htmlSrcdoc"
        />
        <pre
          v-else
          class="min-h-0 flex-1 overflow-auto whitespace-pre-wrap break-words bg-white px-4 py-3 font-mono text-sm leading-relaxed text-slate-800 dark:bg-slate-900 dark:text-slate-100"
        >{{ textContent }}</pre>
      </div>

      <div
        v-else-if="kind === 'image' || file.is_image"
        class="flex min-h-0 flex-1 items-center justify-center overflow-hidden bg-slate-50 dark:bg-slate-950"
      >
        <img
          :src="file.url"
          :alt="file.original_name"
          class="max-h-full max-w-full object-contain"
        >
      </div>

      <div
        v-else-if="kind === 'pdf' || file.is_pdf"
        class="doc-preview-pdf min-h-0 flex-1"
      >
        <iframe
          :src="pdfPreviewUrl"
          class="doc-preview-pdf__iframe"
          title="Xem trước PDF"
        />
      </div>

      <div
        v-else-if="isGoogleEmbed"
        class="doc-preview-google min-h-0 flex-1"
      >
        <iframe
          :src="googleEmbedUrl"
          class="doc-preview-google__iframe"
          :title="kind === 'google_sheet' ? 'Google Sheets' : 'Google Docs'"
          allow="clipboard-read; clipboard-write"
          referrerpolicy="no-referrer-when-downgrade"
        />
      </div>

      <div
        v-else-if="kind === 'docx'"
        class="doc-preview-docx min-h-0 flex-1"
      >
        <iframe
          :ref="setDocxIframe"
          class="doc-preview-docx__iframe"
          title="Xem trước Word"
          :srcdoc="docxSrcdoc"
        />
      </div>

      <div
        v-else-if="kind === 'xlsx'"
        class="flex min-h-0 flex-1 flex-col overflow-hidden bg-white dark:bg-white"
      >
        <div
          v-if="xlsxSheetNames.length > 1"
          class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-100 bg-slate-50 px-2 py-1.5"
        >
          <button
            v-for="name in xlsxSheetNames"
            :key="name"
            type="button"
            class="shrink-0 rounded-md px-2.5 py-1 text-sm font-medium transition"
            :class="activeSheet === name
              ? 'bg-brand text-white'
              : 'text-slate-600 hover:bg-slate-200'"
            @click="switchSheet(name)"
          >
            {{ name }}
          </button>
        </div>
        <div
          class="xlsx-preview-scroll min-h-0 flex-1 overflow-auto"
          v-html="xlsxHtml"
        />
        <div
          v-if="showXlsxPager"
          class="doc-preview-pager"
        >
          <button
            type="button"
            class="doc-preview-pager__btn"
            :disabled="xlsxPage <= 1"
            @click="prevXlsxPage"
          >
            <AppIcon
              name="chevron-left"
              :size="14"
            />
            Trước
          </button>
          <span class="doc-preview-pager__label tabular-nums">
            {{ xlsxRowLabel }}
            <span
              v-if="xlsxPageCount > 1"
              class="text-slate-400"
            > · Trang {{ xlsxPage }} / {{ xlsxPageCount }}</span>
          </span>
          <button
            type="button"
            class="doc-preview-pager__btn"
            :disabled="xlsxPage >= xlsxPageCount"
            @click="nextXlsxPage"
          >
            Sau
            <AppIcon
              name="chevron-right"
              :size="14"
            />
          </button>
        </div>
      </div>

      <div
        v-else-if="showFallback"
        class="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 text-center"
      >
        <AppIcon
          name="template"
          :size="36"
          class="text-slate-300"
        />
        <p class="text-sm text-slate-500">
          Không hỗ trợ xem trước loại file này
        </p>
        <a
          v-if="file.url"
          :href="file.url"
          target="_blank"
          class="text-sm font-medium text-brand hover:underline"
        >Tải xuống</a>
      </div>
    </div>
  </div>
</template>

<style scoped>
.doc-preview-html__iframe {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 0;
    border: 0;
    background: #fff;
}

.doc-preview-markdown :deep(a) {
    color: #9a0036;
}

.doc-preview-pdf {
    height: 100%;
    min-height: 0;
    width: 100%;
    overflow: hidden;
}

.doc-preview-pdf__iframe {
    display: block;
    height: 100%;
    min-height: 0;
    width: 100%;
    border: 0;
    background: #fff;
}

.doc-preview-docx,
.doc-preview-google {
    position: relative;
    flex: 1 1 auto;
    height: 100%;
    min-height: 0;
    width: 100%;
    overflow: hidden;
    background: #eceff3;
}

.doc-preview-docx__iframe,
.doc-preview-google__iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: calc(100% + 2px);
    border: 0;
    background: #eceff3;
}

.doc-preview-google {
    background: #fff;
}

.doc-preview-google__iframe {
    background: #fff;
}

:global(.dark) .doc-preview-docx,
:global(.dark) .doc-preview-google {
    background: rgb(15 23 42);
}

.doc-preview-pager {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    border-top: 1px solid rgb(226 232 240);
    background: rgb(248 250 252);
    padding: 0.5rem 0.75rem;
}

:global(.dark) .doc-preview-pager {
    border-top-color: rgb(51 65 85);
    background: rgb(15 23 42);
}

.doc-preview-pager__btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border-radius: 0.375rem;
    border: 1px solid rgb(226 232 240);
    background: #fff;
    padding: 0.375rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(51 65 85);
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}

.doc-preview-pager__btn:hover:not(:disabled) {
    border-color: rgb(203 213 225);
    background: rgb(248 250 252);
    color: rgb(15 23 42);
}

.doc-preview-pager__btn:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}

:global(.dark) .doc-preview-pager__btn {
    border-color: rgb(51 65 85);
    background: rgb(30 41 59);
    color: rgb(226 232 240);
}

:global(.dark) .doc-preview-pager__btn:hover:not(:disabled) {
    background: rgb(51 65 85);
}

.doc-preview-pager__label {
    min-width: 8rem;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(71 85 105);
}

:global(.dark) .doc-preview-pager__label {
    color: rgb(203 213 225);
}

.xlsx-preview-scroll {
    background: #fff;
}

/*
 * Bảng Excel giữ định dạng gốc: độ rộng cột do <colgroup> quyết định,
 * màu/border/căn lề do style inline của từng ô. CSS ở đây chỉ dựng khung
 * lưới + thanh tiêu đề A/B/C và 1/2/3 kiểu Excel — không ghi đè style ô.
 */
.xlsx-preview-scroll :deep(.xr-table) {
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    width: max-content;
    font-size: 13px;
    line-height: 1.35;
    color: #0f172a;
    background: #fff;
}

.xlsx-preview-scroll :deep(.xr-table td) {
    padding: 3px 6px;
    vertical-align: middle;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    background-clip: padding-box;
}

.xlsx-preview-scroll :deep(.xr-grid td) {
    border-right: 1px solid #e8ecf1;
    border-bottom: 1px solid #e8ecf1;
}

/* Thanh nhãn cột A/B/C — dính trên khi cuộn dọc */
.xlsx-preview-scroll :deep(.xr-colhead),
.xlsx-preview-scroll :deep(.xr-corner) {
    position: sticky;
    top: 0;
    z-index: 2;
    height: 22px;
    padding: 2px 6px;
    background: #f1f5f9;
    border-right: 1px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-align: center;
    user-select: none;
}

/* Nhãn dòng 1/2/3 — dính trái khi cuộn ngang */
.xlsx-preview-scroll :deep(.xr-rowhead) {
    position: sticky;
    left: 0;
    z-index: 1;
    width: 44px;
    min-width: 44px;
    padding: 2px 6px;
    background: #f1f5f9;
    border-right: 1px solid #cbd5e1;
    border-bottom: 1px solid #e8ecf1;
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-align: center;
    user-select: none;
}

/* Góc trái trên phải nằm trên cả hai thanh */
.xlsx-preview-scroll :deep(.xr-corner) {
    left: 0;
    z-index: 3;
    width: 44px;
    min-width: 44px;
}

.xlsx-preview-scroll :deep(.xr-gutter) {
    width: 44px;
}

.doc-preview-toolbar {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.375rem;
    border-bottom: 1px solid rgb(226 232 240);
    background: rgb(248 250 252);
    padding: 0.375rem 0.625rem;
}

:global(.dark) .doc-preview-toolbar {
    border-bottom-color: rgb(51 65 85);
    background: rgb(15 23 42);
}

.doc-preview-toolbar__group {
    display: inline-flex;
    align-items: center;
    gap: 0.125rem;
}

.doc-preview-toolbar__spacer {
    flex: 1 1 auto;
}

.doc-preview-toolbar__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    height: 1.75rem;
    min-width: 1.75rem;
    padding: 0 0.375rem;
    border-radius: 0.375rem;
    border: 1px solid transparent;
    color: rgb(71 85 105);
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}

.doc-preview-toolbar__btn:hover:not(:disabled) {
    background: rgb(226 232 240);
    color: rgb(15 23 42);
}

.doc-preview-toolbar__btn:disabled {
    cursor: not-allowed;
    opacity: 0.4;
}

:global(.dark) .doc-preview-toolbar__btn {
    color: rgb(203 213 225);
}

:global(.dark) .doc-preview-toolbar__btn:hover:not(:disabled) {
    background: rgb(51 65 85);
    color: #fff;
}

.doc-preview-toolbar__zoom {
    min-width: 4.5rem;
    height: 1.75rem;
    padding: 0 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(71 85 105);
    transition: background 0.15s;
}

.doc-preview-toolbar__zoom:hover {
    background: rgb(226 232 240);
}

:global(.dark) .doc-preview-toolbar__zoom {
    color: rgb(203 213 225);
}

:global(.dark) .doc-preview-toolbar__zoom:hover {
    background: rgb(51 65 85);
}
</style>
