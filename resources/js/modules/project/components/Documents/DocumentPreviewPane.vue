<script setup>
/* eslint-disable vue/no-v-html -- xlsx sheet preview HTML */
import { computed, toRef, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useDocumentPreview } from '@/composables/useDocumentPreview';

const props = defineProps({
    file: { type: Object, default: null },
    editing: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['update:draft', 'update:editing']);

const fileRef = toRef(() => props.file);

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
    pageCount,
    currentPage,
    nextPage,
    prevPage,
    xlsxPage,
    xlsxPageCount,
    xlsxRowLabel,
    nextXlsxPage,
    prevXlsxPage,
} = useDocumentPreview(fileRef);

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

const showDocxPager = computed(() =>
    kind.value === 'docx' && !loading.value && !error.value && pageCount.value >= 1,
);

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
        v-if="loading"
        class="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 text-slate-500"
      >
        <AppIcon
          name="refresh"
          :size="22"
          class="animate-spin text-brand"
        />
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
        v-else-if="kind === 'text'"
        class="flex min-h-0 flex-1 flex-col"
      >
        <textarea
          v-if="editing && canEdit"
          v-model="draft"
          class="min-h-0 flex-1 resize-none border-0 bg-slate-50 px-4 py-3 font-mono text-sm leading-relaxed text-slate-800 outline-none focus:ring-0 dark:bg-slate-950 dark:text-slate-100"
          spellcheck="false"
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
        class="flex min-h-0 flex-1 flex-col overflow-hidden bg-slate-100 dark:bg-slate-900"
      >
        <iframe
          :ref="setDocxIframe"
          class="doc-preview-docx__iframe min-h-0 flex-1"
          title="Xem trước Word"
        />
        <div
          v-if="showDocxPager"
          class="doc-preview-pager"
        >
          <button
            type="button"
            class="doc-preview-pager__btn"
            :disabled="currentPage <= 1"
            @click="prevPage"
          >
            <AppIcon
              name="chevron-left"
              :size="14"
            />
            Trước
          </button>
          <span class="doc-preview-pager__label tabular-nums">
            Trang {{ currentPage }} / {{ pageCount }}
          </span>
          <button
            type="button"
            class="doc-preview-pager__btn"
            :disabled="currentPage >= pageCount"
            @click="nextPage"
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

.doc-preview-docx__iframe {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 0;
    border: 0;
    background: #f1f5f9;
}

.doc-preview-google {
    position: relative;
    flex: 1 1 auto;
    height: 100%;
    min-height: 0;
    width: 100%;
    overflow: hidden;
    background: #fff;
}

.doc-preview-google__iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: calc(100% + 2px);
    border: 0;
    background: #fff;
}

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

.xlsx-preview-scroll :deep(.xlsx-preview-table) {
    width: max-content;
    min-width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
    line-height: 1.35;
    color: #0f172a;
}

.xlsx-preview-scroll :deep(.xlsx-preview-table th),
.xlsx-preview-scroll :deep(.xlsx-preview-table td) {
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.5rem 0.75rem;
    vertical-align: top;
    text-align: left;
    white-space: nowrap;
    max-width: 16rem;
}

.xlsx-preview-scroll :deep(.xlsx-preview-table th) {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f8fafc;
    font-weight: 700;
    color: #334155;
    border-bottom-color: #cbd5e1;
    box-shadow: 0 1px 0 #cbd5e1;
}

.xlsx-preview-scroll :deep(.xlsx-preview-table td.xlsx-row-alt),
.xlsx-preview-scroll :deep(.xlsx-preview-table tr.xlsx-row-alt td) {
    background: #f8fafc;
}

.xlsx-preview-scroll :deep(.xlsx-preview-table th span),
.xlsx-preview-scroll :deep(.xlsx-preview-table td span) {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 15rem;
}
</style>
