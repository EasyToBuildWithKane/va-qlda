<script setup>
/* eslint-disable vue/no-v-html -- server-rendered proposal preview HTML from authenticated API */
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { highlightProposalPreviewPage } from '@/modules/aiAccount/composables/useProposalPreviewPaginatedLayout';
import { useProposalPreviewPageCount } from '@/modules/aiAccount/composables/useProposalPreviewPageCount';

const props = defineProps({
    html: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    zoom: { type: Number, default: 1 },
});

const emit = defineEmits(['refresh']);

const measureHostRef = ref(null);
const pagesStackRef = ref(null);
const currentPage = ref(1);
const htmlRef = computed(() => props.html);
const { pageCount, remeasure } = useProposalPreviewPageCount(
    htmlRef,
    measureHostRef,
    pagesStackRef,
    currentPage,
);

watch(pageCount, (n) => {
    if (currentPage.value > n) currentPage.value = n;
    if (currentPage.value < 1) currentPage.value = 1;
});

watch(() => props.html, () => {
    currentPage.value = 1;
    remeasure();
});

watch(currentPage, (p) => {
    highlightProposalPreviewPage(pagesStackRef.value, p);
    const sheet = pagesStackRef.value?.querySelector(`[data-page="${p}"]`);
    sheet?.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

const pageLabel = computed(() => `${pageCount.value} trang A4`);

function goPage(page) {
    currentPage.value = Math.min(pageCount.value, Math.max(1, page));
}

function goPageDelta(delta) {
    goPage(currentPage.value + delta);
}

function onRefresh() {
    emit('refresh');
    remeasure();
}
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col gap-3">
    <div
      class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white px-4 py-3"
    >
      <div class="min-w-0 space-y-0.5">
        <p class="text-sm font-semibold text-slate-800">
          Xem trước phiếu in (PDF)
        </p>
        <p class="text-xs text-slate-500">
          Mỗi trang A4 có nền letterhead riêng — nội dung không đè lên thanh đỏ footer.
        </p>
      </div>
      <span
        v-if="html && !loading"
        class="inline-flex items-center gap-1.5 rounded-lg border border-brand/20 bg-brand/5 px-2.5 py-1 text-xs font-semibold text-brand"
      >
        <AppIcon
          name="pdf"
          :size="14"
        />
        {{ pageLabel }}
      </span>
    </div>

    <!-- Pagination — luôn hiển thị khi có preview -->
    <div
      v-if="html && !loading && !error"
      class="flex flex-wrap items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2"
    >
      <button
        type="button"
        class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:border-slate-300 disabled:opacity-40"
        :disabled="currentPage <= 1"
        aria-label="Trang trước"
        @click="goPageDelta(-1)"
      >
        <AppIcon
          name="chevron-left"
          :size="14"
        />
        Trước
      </button>
      <div class="flex flex-wrap items-center justify-center gap-1">
        <button
          v-for="p in pageCount"
          :key="p"
          type="button"
          class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs font-semibold tabular-nums transition"
          :class="p === currentPage
            ? 'border-brand bg-brand text-white shadow-sm'
            : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-brand/30 hover:bg-brand/5'"
          :aria-current="p === currentPage ? 'page' : undefined"
          @click="goPage(p)"
        >
          {{ p }}
        </button>
      </div>
      <span class="min-w-[5.5rem] text-center text-xs tabular-nums text-slate-600">
        Trang <strong class="text-slate-800">{{ currentPage }}</strong> / {{ pageCount }}
      </span>
      <button
        type="button"
        class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:border-slate-300 disabled:opacity-40"
        :disabled="currentPage >= pageCount"
        aria-label="Trang sau"
        @click="goPageDelta(1)"
      >
        Sau
        <AppIcon
          name="chevron-right"
          :size="14"
        />
      </button>
    </div>

    <p
      v-if="error"
      class="rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-sm text-rose-700"
    >
      {{ error }}
    </p>

    <div
      v-if="loading"
      class="flex min-h-[min(52vh,480px)] flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/80"
    >
      <AppIcon
        name="refresh"
        :size="22"
        class="animate-spin text-brand"
      />
      <p class="text-sm text-slate-500">
        Đang dựng bản xem trước…
      </p>
    </div>

    <div
      v-else-if="!html"
      class="flex min-h-[min(40vh,360px)] flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-6 text-center"
    >
      <AppIcon
        name="pdf"
        :size="32"
        class="text-slate-300"
      />
      <p class="text-sm text-slate-600">
        Điền các tab trước, rồi mở lại tab này để xem phiếu in.
      </p>
    </div>

    <template v-else>
      <!-- Nguồn đo chiều cao (ẩn) -->
      <div
        ref="measureHostRef"
        class="proposal-preview-measure-host"
        aria-hidden="true"
        v-html="html"
      />

      <div
        class="proposal-preview-viewport min-h-[min(52vh,480px)] flex-1 overflow-auto rounded-xl border border-slate-200 bg-[#525659] p-4 sm:p-6"
      >
        <div
          class="mx-auto transition-transform duration-150"
          :style="{
            transform: `scale(${zoom})`,
            transformOrigin: 'top center',
            width: '210mm',
          }"
        >
          <div
            ref="pagesStackRef"
            class="proposal-preview-pages-stack"
          />
        </div>
      </div>
    </template>

    <div class="flex justify-end">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-brand disabled:opacity-40"
        :disabled="loading || !html"
        @click="onRefresh"
      >
        <AppIcon
          name="refresh"
          :size="14"
        />
        Tải lại xem trước
      </button>
    </div>
  </div>
</template>

<style>
.proposal-preview-measure-host {
    position: absolute;
    left: -10000px;
    top: 0;
    width: 210mm;
    visibility: hidden;
    pointer-events: none;
    overflow: hidden;
}

.proposal-preview-viewport {
    scrollbar-color: rgba(255, 255, 255, 0.35) transparent;
}

.proposal-preview-pages-stack {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    align-items: center;
}

.proposal-preview-pages-stack :deep(.proposal-preview-page-sheet) {
    position: relative;
    width: 210mm;
    height: 297mm;
    overflow: hidden;
    flex-shrink: 0;
    background: #fff;
    box-shadow:
        0 0 0 1px rgba(15, 23, 42, 0.08),
        0 12px 40px rgba(15, 23, 42, 0.35);
}

.proposal-preview-pages-stack :deep(.proposal-preview-page-bg) {
    position: absolute;
    inset: 0;
    z-index: 0;
    width: 100%;
    height: 100%;
    object-fit: fill;
    pointer-events: none;
}

.proposal-preview-pages-stack :deep(.proposal-preview-page-clip) {
    position: absolute;
    z-index: 1;
    top: 42mm;
    left: 14mm;
    right: 12mm;
    bottom: calc(15mm + 20mm);
    overflow: hidden;
}

.proposal-preview-pages-stack :deep(.proposal-preview-page-clone) {
    box-sizing: border-box;
    width: 100%;
    margin: 0;
    background: transparent;
}

.proposal-preview-pages-stack :deep(.proposal-preview-page-sheet--current) {
    box-shadow:
        0 0 0 2px rgba(154, 0, 54, 0.45),
        0 12px 40px rgba(15, 23, 42, 0.35);
}

.proposal-preview-pages-stack :deep(.proposal-preview-page-sheet:not(.proposal-preview-page-sheet--current)) {
    opacity: 0.92;
}

.proposal-preview-pages-stack :deep(.proposal-preview-page-badge) {
    position: absolute;
    z-index: 2;
    right: 10mm;
    bottom: 6mm;
    padding: 2px 8px;
    border-radius: 4px;
    background: rgba(15, 23, 42, 0.72);
    color: #fff;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.03em;
    pointer-events: none;
}
</style>
