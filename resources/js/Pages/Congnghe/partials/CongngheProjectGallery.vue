<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    images: { type: Array, default: () => [] },
    /** slide = panel dự án/sản phẩm; modal = chi tiết */
    density: {
        type: String,
        default: 'slide',
        validator: (v) => ['slide', 'modal'].includes(v),
    },
    emptyHint: {
        type: String,
        default: 'Chưa có hình ảnh tham chiếu. Ảnh được cập nhật từ tab Tài liệu dự án → «Hình ảnh cổng Công nghệ».',
    },
});

const selectedIndex = ref(0);
const lightboxOpen = ref(false);

const list = computed(() => (props.images ?? []).filter((img) => img?.url));

watch(
    () => list.value.map((i) => i.id).join(','),
    () => {
        selectedIndex.value = 0;
        lightboxOpen.value = false;
    },
);

const active = computed(() => list.value[selectedIndex.value] ?? null);

const previewFrameClass = computed(() =>
    props.density === 'modal'
        ? 'min-h-[min(42vh,280px)] max-h-[min(52vh,420px)]'
        : 'min-h-40 max-h-52 sm:min-h-44 sm:max-h-60',
);

function select(index) {
    if (index < 0 || index >= list.value.length) {
        return;
    }
    selectedIndex.value = index;
}

function openLightbox() {
    if (active.value?.url) {
        lightboxOpen.value = true;
    }
}

function closeLightbox() {
    lightboxOpen.value = false;
}

function onLightboxKey(e) {
    if (e.key === 'Escape') {
        closeLightbox();
    } else if (e.key === 'ArrowLeft' && list.value.length > 1) {
        select((selectedIndex.value - 1 + list.value.length) % list.value.length);
    } else if (e.key === 'ArrowRight' && list.value.length > 1) {
        select((selectedIndex.value + 1) % list.value.length);
    }
}

watch(lightboxOpen, (open) => {
    if (typeof document === 'undefined') {
        return;
    }
    if (open) {
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onLightboxKey);
    } else {
        document.body.style.overflow = '';
        window.removeEventListener('keydown', onLightboxKey);
    }
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
    window.removeEventListener('keydown', onLightboxKey);
});
</script>

<template>
  <div class="flex min-h-0 flex-col">
    <p
      v-if="density === 'slide'"
      class="font-mono text-[10px] uppercase tracking-[0.18em] text-white/40"
    >
      Hình ảnh tham chiếu
    </p>

    <div
      v-if="active"
      class="mt-3 overflow-hidden rounded-2xl border border-white/10 bg-black/40"
      :class="density === 'modal' ? 'mt-4' : ''"
    >
      <button
        type="button"
        class="group relative flex w-full cursor-zoom-in items-center justify-center p-2 transition hover:bg-black/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        :class="previewFrameClass"
        aria-label="Phóng to ảnh gốc"
        @click="openLightbox"
      >
        <img
          :key="active.id"
          :src="active.url"
          alt=""
          class="max-h-full max-w-full object-contain object-center"
          loading="lazy"
          decoding="async"
        >
        <span class="pointer-events-none absolute bottom-2 right-2 rounded-full border border-white/15 bg-black/50 px-2 py-0.5 text-[10px] font-medium text-white/70 opacity-0 transition group-hover:opacity-100">
          Phóng to
        </span>
      </button>
    </div>

    <div
      v-else
      class="mt-3 flex flex-col items-center justify-center rounded-2xl border border-dashed border-white/12 bg-white/[0.02] px-4 text-center"
      :class="previewFrameClass"
    >
      <span class="grid h-11 w-11 place-items-center rounded-xl border border-white/15 bg-white/5 text-white/35">
        <svg
          width="22"
          height="22"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
        ><rect
          x="3"
          y="3"
          width="18"
          height="18"
          rx="2"
        /><circle
          cx="8.5"
          cy="8.5"
          r="1.5"
        /><path d="m21 15-5-5L5 21" /></svg>
      </span>
      <p class="mt-3 max-w-xs text-xs leading-relaxed text-white/45">
        {{ emptyHint }}
      </p>
    </div>

    <div
      v-if="list.length > 1"
      class="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-5"
      role="listbox"
      aria-label="Chọn ảnh xem"
    >
      <button
        v-for="(img, idx) in list"
        :key="img.id"
        type="button"
        role="option"
        :aria-selected="idx === selectedIndex"
        :aria-label="`Ảnh ${idx + 1}`"
        class="relative flex aspect-[4/3] w-full items-center justify-center overflow-hidden rounded-lg border-2 bg-black/30 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        :class="idx === selectedIndex
          ? 'border-brand shadow-[0_0_16px_-4px_rgba(154,0,54,0.85)]'
          : 'border-white/15 opacity-85 hover:border-white/35 hover:opacity-100'"
        @click="select(idx)"
      >
        <img
          :src="img.url"
          alt=""
          class="max-h-full max-w-full object-contain object-center p-0.5"
          loading="lazy"
          decoding="async"
        >
      </button>
    </div>

    <Teleport to="body">
      <Transition name="cn-lightbox">
        <div
          v-if="lightboxOpen && active"
          class="fixed inset-0 z-[130] flex flex-col bg-[#05060c]/95 backdrop-blur-md"
          role="dialog"
          aria-modal="true"
          aria-label="Xem ảnh gốc"
          @click.self="closeLightbox"
        >
          <div class="flex shrink-0 items-center justify-end gap-2 px-4 py-3">
            <span
              v-if="list.length > 1"
              class="mr-auto font-mono text-[11px] tabular-nums text-white/45"
            >
              {{ selectedIndex + 1 }} / {{ list.length }}
            </span>
            <button
              v-if="list.length > 1"
              type="button"
              class="grid h-9 w-9 place-items-center rounded-full border border-white/15 text-white/70 hover:bg-white/10"
              aria-label="Ảnh trước"
              @click="select((selectedIndex - 1 + list.length) % list.length)"
            >
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              ><path d="M15 6l-6 6 6 6" /></svg>
            </button>
            <button
              v-if="list.length > 1"
              type="button"
              class="grid h-9 w-9 place-items-center rounded-full border border-white/15 text-white/70 hover:bg-white/10"
              aria-label="Ảnh sau"
              @click="select((selectedIndex + 1) % list.length)"
            >
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              ><path d="M9 6l6 6-6 6" /></svg>
            </button>
            <button
              type="button"
              class="grid h-9 w-9 place-items-center rounded-full border border-white/15 text-white/70 hover:bg-white/10"
              aria-label="Đóng"
              @click="closeLightbox"
            >
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              ><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="flex min-h-0 flex-1 items-center justify-center px-4 pb-8 pt-2">
            <img
              :key="`lb-${active.id}`"
              :src="active.url"
              alt=""
              class="max-h-[min(88vh,1200px)] max-w-[min(96vw,1400px)] object-contain"
              decoding="async"
            >
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.cn-lightbox-enter-active,
.cn-lightbox-leave-active {
    transition: opacity 0.2s ease;
}

.cn-lightbox-enter-from,
.cn-lightbox-leave-to {
    opacity: 0;
}
</style>
