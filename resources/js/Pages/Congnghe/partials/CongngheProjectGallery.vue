<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

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
const thumbStrip = ref(null);

const list = computed(() => (props.images ?? []).filter((img) => img?.url));

watch(
    () => list.value.map((i) => i.id).join(','),
    () => {
        selectedIndex.value = 0;
        lightboxOpen.value = false;
    },
);

const active = computed(() => list.value[selectedIndex.value] ?? null);

/** Khung preview: aspect-ratio + max-h để img object-contain luôn vừa khung, không bị cắt. */
const previewFrameClass = computed(() =>
    props.density === 'modal'
        ? 'aspect-[4/3] w-full max-h-[min(52vh,420px)] min-h-[min(36vh,240px)]'
        : 'aspect-[4/3] w-full max-h-[min(22rem,48vh)] min-h-[10rem] sm:max-h-[min(24rem,52vh)]',
);

const emptyFrameClass = computed(() =>
    props.density === 'modal'
        ? 'min-h-[min(36vh,240px)] max-h-[min(52vh,420px)]'
        : 'min-h-[10rem] max-h-[min(22rem,48vh)] sm:max-h-[min(24rem,52vh)]',
);

function select(index) {
    if (index < 0 || index >= list.value.length) {
        return;
    }
    selectedIndex.value = index;
    scrollThumbIntoView(index);
}

function selectPrev() {
    select((selectedIndex.value - 1 + list.value.length) % list.value.length);
}

function selectNext() {
    select((selectedIndex.value + 1) % list.value.length);
}

/** Cuộn thanh thumbnail để thumbnail đang chọn luôn hiển thị trong khung. */
function scrollThumbIntoView(idx) {
    nextTick(() => {
        const strip = thumbStrip.value;
        if (!strip) return;
        const btn = strip.children[idx];
        if (!btn) return;
        const { left: bLeft, right: bRight } = btn.getBoundingClientRect();
        const { left: sLeft, right: sRight } = strip.getBoundingClientRect();
        if (bLeft < sLeft) {
            strip.scrollBy({ left: bLeft - sLeft - 8, behavior: 'smooth' });
        } else if (bRight > sRight) {
            strip.scrollBy({ left: bRight - sRight + 8, behavior: 'smooth' });
        }
    });
}

function scrollThumbPage(dir) {
    const strip = thumbStrip.value;
    if (!strip) return;
    strip.scrollBy({ left: dir * strip.clientWidth * 0.75, behavior: 'smooth' });
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
        selectPrev();
    } else if (e.key === 'ArrowRight' && list.value.length > 1) {
        selectNext();
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

    <!-- Khung ảnh chính + mũi tên overlay -->
    <div
      v-if="active"
      class="group/frame relative mt-3 overflow-hidden rounded-2xl border border-white/10 bg-black/40"
      :class="density === 'modal' ? 'mt-4' : ''"
    >
      <button
        type="button"
        class="relative block w-full cursor-zoom-in transition hover:bg-black/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        :class="previewFrameClass"
        aria-label="Phóng to ảnh gốc"
        @click="openLightbox"
      >
        <span class="absolute inset-0 flex items-center justify-center p-2">
          <img
            :key="active.id"
            :src="active.url"
            alt=""
            class="max-h-full max-w-full object-contain object-center"
            loading="lazy"
            decoding="async"
          >
        </span>
        <span class="pointer-events-none absolute bottom-2 right-2 rounded-full border border-white/15 bg-black/50 px-2 py-0.5 text-[10px] font-medium text-white/70 opacity-0 transition group-hover/frame:opacity-100">
          Phóng to
        </span>
      </button>

      <!-- Mũi tên trái / phải overlay trên ảnh chính -->
      <template v-if="list.length > 1">
        <button
          type="button"
          class="cn-gal-arrow left-2 opacity-0 group-hover/frame:opacity-100"
          aria-label="Ảnh trước"
          @click.stop="selectPrev"
        >
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
          ><path d="M15 6l-6 6 6 6" /></svg>
        </button>
        <button
          type="button"
          class="cn-gal-arrow right-2 opacity-0 group-hover/frame:opacity-100"
          aria-label="Ảnh sau"
          @click.stop="selectNext"
        >
          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.5"
          ><path d="M9 6l6 6-6 6" /></svg>
        </button>
      </template>

      <!-- Đếm ảnh góc trên trái -->
      <span
        v-if="list.length > 1"
        class="pointer-events-none absolute left-2 top-2 rounded-full border border-white/15 bg-black/55 px-2 py-0.5 font-mono text-[10px] tabular-nums text-white/65 opacity-0 transition group-hover/frame:opacity-100"
      >
        {{ selectedIndex + 1 }} / {{ list.length }}
      </span>
    </div>

    <div
      v-else
      class="mt-3 flex flex-col items-center justify-center rounded-2xl border border-dashed border-white/12 bg-white/[0.02] px-4 text-center"
      :class="emptyFrameClass"
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

    <!-- Thanh thumbnail: ẩn scrollbar gốc, thêm nút điều hướng hai bên -->
    <div
      v-if="list.length > 1"
      class="mt-3 flex items-center gap-1.5"
    >
      <!-- Nút lùi thumbnail -->
      <button
        type="button"
        class="cn-thumb-nav shrink-0"
        aria-label="Thumbnail trước"
        @click="scrollThumbPage(-1)"
      >
        <svg
          width="13"
          height="13"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
        ><path d="M15 6l-6 6 6 6" /></svg>
      </button>

      <!-- Strip thumbnail: no scrollbar -->
      <div
        ref="thumbStrip"
        class="flex min-w-0 flex-1 gap-2 overflow-x-auto overscroll-x-contain scroll-smooth [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
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
          class="relative flex h-16 w-[4.5rem] shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 bg-black/30 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:h-[4.25rem] sm:w-20"
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

      <!-- Nút tới thumbnail -->
      <button
        type="button"
        class="cn-thumb-nav shrink-0"
        aria-label="Thumbnail sau"
        @click="scrollThumbPage(1)"
      >
        <svg
          width="13"
          height="13"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.5"
        ><path d="M9 6l6 6-6 6" /></svg>
      </button>
    </div>

    <!-- Lightbox toàn màn hình -->
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
              @click="selectPrev"
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
              @click="selectNext"
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
/* Mũi tên overlay trên ảnh chính */
.cn-gal-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    display: grid;
    place-items: center;
    height: 2.25rem;
    width: 2.25rem;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(5, 6, 12, 0.72);
    color: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(6px);
    transition: opacity 0.2s, border-color 0.2s, background 0.2s, box-shadow 0.2s;
}

.cn-gal-arrow:hover {
    border-color: rgba(255, 255, 255, 0.38);
    background: rgba(5, 6, 12, 0.9);
    box-shadow: 0 0 18px -6px rgba(154, 0, 54, 0.7);
}

/* Nút điều hướng thumbnail */
.cn-thumb-nav {
    display: grid;
    place-items: center;
    height: 2rem;
    width: 1.75rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    color: rgba(255, 255, 255, 0.5);
    transition: border-color 0.2s, color 0.2s, background 0.2s;
    flex-shrink: 0;
}

.cn-thumb-nav:hover {
    border-color: rgba(255, 255, 255, 0.28);
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.85);
}

.cn-lightbox-enter-active,
.cn-lightbox-leave-active {
    transition: opacity 0.2s ease;
}

.cn-lightbox-enter-from,
.cn-lightbox-leave-to {
    opacity: 0;
}
</style>
