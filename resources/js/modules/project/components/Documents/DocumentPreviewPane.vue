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
    setDocxContainer,
    switchSheet,
    reload,
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
        class="min-h-0 flex-1 overflow-auto bg-white p-3 dark:bg-white"
      >
        <div
          :ref="setDocxContainer"
          class="docx-wrapper text-base text-slate-800"
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
          class="min-h-0 flex-1 overflow-auto p-2 text-sm [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-slate-200 [&_td]:px-2 [&_td]:py-1.5 [&_th]:border [&_th]:border-slate-200 [&_th]:bg-slate-50 [&_th]:px-2 [&_th]:py-1.5"
          v-html="xlsxHtml"
        />
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
.docx-wrapper :deep(.docx-preview-content) {
    max-width: 100%;
}
.docx-wrapper :deep(section.docx) {
    margin-bottom: 0.5rem;
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
</style>
