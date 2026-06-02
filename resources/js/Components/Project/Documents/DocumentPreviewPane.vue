<script setup>
import { computed, toRef } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useDocumentPreview } from '@/composables/useDocumentPreview';

const props = defineProps({
    file: { type: Object, default: null },
});

const fileRef = toRef(() => props.file);

const {
    kind,
    loading,
    error,
    xlsxHtml,
    xlsxSheetNames,
    activeSheet,
    setDocxContainer,
    switchSheet,
} = useDocumentPreview(fileRef);

const showFallback = computed(() =>
    props.file && !loading.value && !error.value
    && kind.value === 'none',
);
</script>

<template>
    <div v-if="!file" class="flex h-full min-h-[200px] items-center justify-center text-slate-400">
        <p class="text-sm">Chọn file để xem trước.</p>
    </div>

    <template v-else>
        <div v-if="loading" class="flex h-full min-h-[200px] flex-col items-center justify-center gap-2 text-slate-500">
            <AppIcon name="refresh" :size="24" class="animate-spin text-brand" />
            <p class="text-sm">Đang tải xem trước…</p>
        </div>

        <div v-else-if="error" class="flex h-full min-h-[200px] flex-col items-center justify-center px-6 text-center">
            <AppIcon name="info" :size="32" class="text-amber-500" />
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ error }}</p>
            <a :href="file.url" target="_blank" class="mt-2 text-sm font-medium text-brand hover:underline">Tải xuống</a>
        </div>

        <img
            v-else-if="kind === 'image' || file.is_image"
            :src="file.url"
            :alt="file.original_name"
            class="mx-auto max-h-full max-w-full rounded-lg object-contain shadow-sm"
        />

        <iframe
            v-else-if="kind === 'pdf' || file.is_pdf"
            :src="file.url"
            class="h-full min-h-[320px] w-full rounded-lg border border-slate-200 bg-white dark:border-slate-600"
            title="Xem trước PDF"
        />

        <div v-else-if="kind === 'docx'" class="h-full min-h-[320px] overflow-auto rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-600 dark:bg-white">
            <div :ref="setDocxContainer" class="docx-wrapper text-sm text-slate-800" />
        </div>

        <div v-else-if="kind === 'xlsx'" class="flex h-full min-h-[320px] flex-col overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-600">
            <div v-if="xlsxSheetNames.length > 1" class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-100 bg-slate-50 px-2 py-1.5">
                <button
                    v-for="name in xlsxSheetNames"
                    :key="name"
                    type="button"
                    class="shrink-0 rounded-md px-2.5 py-1 text-xs font-medium transition"
                    :class="activeSheet === name
                        ? 'bg-brand text-white'
                        : 'text-slate-600 hover:bg-slate-200'"
                    @click="switchSheet(name)"
                >
                    {{ name }}
                </button>
            </div>
            <div
                class="min-h-0 flex-1 overflow-auto p-2 text-xs [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-slate-200 [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:border-slate-200 [&_th]:bg-slate-50 [&_th]:px-2 [&_th]:py-1"
                v-html="xlsxHtml"
            />
        </div>

        <div v-else-if="showFallback" class="flex h-full min-h-[200px] flex-col items-center justify-center text-center">
            <AppIcon name="template" :size="40" class="text-slate-300" />
            <p class="mt-2 text-sm text-slate-500">Không hỗ trợ xem trước loại file này.</p>
            <p class="mt-1 text-xs text-slate-400">Hỗ trợ: ảnh, PDF, DOCX, XLSX/XLS</p>
            <a :href="file.url" target="_blank" class="mt-2 text-sm font-medium text-brand hover:underline">Tải xuống để xem</a>
        </div>
    </template>
</template>

<style>
/* docx-preview wrapper defaults */
.docx-wrapper .docx-preview-content {
    max-width: 100%;
}
.docx-wrapper section.docx {
    margin-bottom: 0.5rem;
}
</style>
