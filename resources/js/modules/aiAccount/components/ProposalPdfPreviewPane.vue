<script setup>
/* eslint-disable vue/no-v-html -- server-rendered proposal preview HTML from authenticated API */
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import {
    PROPOSAL_PREVIEW_PAGE_HEIGHT_MM,
    useProposalPreviewPageCount,
} from '@/modules/aiAccount/composables/useProposalPreviewPageCount';

const props = defineProps({
    html: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    zoom: { type: Number, default: 1 },
});

const emit = defineEmits(['refresh']);

const hostRef = ref(null);
const scrollRef = ref(null);
const htmlRef = computed(() => props.html);
const { pageCount, remeasure } = useProposalPreviewPageCount(htmlRef, hostRef);

const viewMode = ref('all');
const currentPage = ref(1);

watch(pageCount, (n) => {
    if (currentPage.value > n) currentPage.value = n;
});

watch(() => props.html, () => {
    currentPage.value = 1;
    remeasure();
});

const pageLabel = computed(() => {
    if (pageCount.value <= 1) return '1 trang A4';
    return `${pageCount.value} trang A4`;
});

function mmToPx(mm) {
    return (mm * 96) / 25.4;
}

function scrollToPage(page) {
    const scroller = scrollRef.value;
    if (!scroller) return;
    const top = (page - 1) * mmToPx(PROPOSAL_PREVIEW_PAGE_HEIGHT_MM);
    scroller.scrollTo({ top: top * props.zoom, behavior: 'smooth' });
    currentPage.value = page;
}

function goPage(delta) {
    scrollToPage(Math.min(pageCount.value, Math.max(1, currentPage.value + delta)));
}

watch(viewMode, (mode) => {
    if (mode === 'single') scrollToPage(currentPage.value);
});
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col gap-3">
    <div
      class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white px-4 py-3"
    >
      <div class="min-w-0 space-y-0.5">
        <p class="text-sm font-semibold text-slate-800">
          Xem trước phiếu in
        </p>
        <p class="text-xs text-slate-500">
          Khớp PDF xuất · nền letterhead · lề A4 (42mm / 12mm / 15mm / 14mm). Đường đỏ ngăn trang.
        </p>
      </div>
      <div
        v-if="html && !loading"
        class="flex flex-wrap items-center gap-2"
      >
        <span
          class="inline-flex items-center gap-1.5 rounded-lg border border-brand/20 bg-brand/5 px-2.5 py-1 text-xs font-semibold text-brand"
        >
          <AppIcon
            name="pdf"
            :size="14"
          />
          {{ pageLabel }}
        </span>
        <div
          v-if="pageCount > 1"
          class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-xs"
        >
          <button
            type="button"
            class="rounded-md px-2.5 py-1 font-medium transition"
            :class="viewMode === 'all'
              ? 'bg-slate-100 text-slate-800'
              : 'text-slate-500 hover:text-slate-700'"
            @click="viewMode = 'all'"
          >
            Cuộn tất cả
          </button>
          <button
            type="button"
            class="rounded-md px-2.5 py-1 font-medium transition"
            :class="viewMode === 'single'
              ? 'bg-slate-100 text-slate-800'
              : 'text-slate-500 hover:text-slate-700'"
            @click="viewMode = 'single'"
          >
            Từng trang
          </button>
        </div>
      </div>
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
      <div
        v-if="pageCount > 1"
        class="flex flex-wrap items-center justify-center gap-2"
      >
        <template v-if="viewMode === 'single'">
          <button
            type="button"
            class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:border-slate-300 disabled:opacity-40"
            :disabled="currentPage <= 1"
            @click="goPage(-1)"
          >
            <AppIcon
              name="chevron-left"
              :size="14"
            />
            Trang trước
          </button>
          <span class="tabular-nums text-sm text-slate-700">
            Trang <strong>{{ currentPage }}</strong> / {{ pageCount }}
          </span>
          <button
            type="button"
            class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:border-slate-300 disabled:opacity-40"
            :disabled="currentPage >= pageCount"
            @click="goPage(1)"
          >
            Trang sau
            <AppIcon
              name="chevron-right"
              :size="14"
            />
          </button>
        </template>
        <div
          v-else
          class="flex flex-wrap justify-center gap-1"
        >
          <button
            v-for="p in pageCount"
            :key="p"
            type="button"
            class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-1.5 text-xs font-medium tabular-nums transition"
            :class="p === currentPage
              ? 'border-brand/40 bg-brand/10 text-brand'
              : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
            @click="scrollToPage(p)"
          >
            {{ p }}
          </button>
        </div>
      </div>

      <div
        ref="scrollRef"
        class="proposal-preview-viewport min-h-[min(52vh,480px)] flex-1 overflow-auto rounded-xl border border-slate-200 bg-[#525659] p-4 sm:p-6"
        :class="viewMode === 'single' ? 'proposal-preview-viewport--single max-h-[min(72vh,820px)]' : ''"
      >
        <div
          class="mx-auto shadow-2xl transition-transform duration-150"
          :style="{
            transform: `scale(${zoom})`,
            transformOrigin: 'top center',
            width: '210mm',
          }"
        >
          <div
            ref="hostRef"
            class="proposal-pdf-preview bg-white"
            v-html="html"
          />
        </div>
      </div>
    </template>

    <div class="flex justify-end">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-brand disabled:opacity-40"
        :disabled="loading || !html"
        @click="emit('refresh')"
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
.proposal-preview-viewport {
    scrollbar-color: rgba(255, 255, 255, 0.35) transparent;
}

/* Chế độ từng trang: khung cao ~1 trang A4 (297mm × zoom xấp xỉ) */
.proposal-preview-viewport--single {
    scroll-snap-type: y mandatory;
}

.proposal-preview-viewport--single .proposal-pdf-preview {
    scroll-snap-align: start;
}

.proposal-pdf-preview .proposal-preview-strip {
    box-shadow:
        0 0 0 1px rgba(15, 23, 42, 0.06),
        0 8px 30px rgba(15, 23, 42, 0.2);
}
</style>
