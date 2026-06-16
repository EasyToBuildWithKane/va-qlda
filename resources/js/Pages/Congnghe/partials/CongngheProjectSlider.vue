<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ProjectShowcaseCard from './ProjectShowcaseCard.vue';

/**
 * Băng chuyền dự án dùng chung cho "Vòng đời sản phẩm số" và "Hệ sinh thái sản
 * phẩm": một hàng, 3 thẻ/khung trên desktop (2 trên tablet, ~1.1 trên mobile),
 * kéo ngang bằng chuột hoặc vuốt cảm ứng. Nút lùi/tới + thanh tiến độ cuộn ở
 * dưới. Khi vừa kéo (>8px) sẽ chặn click mở modal để không mở nhầm dự án.
 */
const props = defineProps({
    projects: { type: Array, default: () => [] },
    // Màu nhấn cho nút điều hướng + thanh tiến độ.
    accent: { type: String, default: 'brand' },
    // Đổi giá trị ⇒ cuộn về đầu (vd. khi đổi giai đoạn vòng đời).
    resetKey: { type: [String, Number], default: '' },
});

const ACCENTS = {
    brand: {
        btn: 'enabled:hover:border-brand/55 enabled:hover:text-white enabled:hover:shadow-[0_0_22px_-6px_rgba(255,77,141,0.75)]',
        bar: 'bg-[linear-gradient(110deg,#9A0036,#ff4d8d)]',
        ring: 'focus-visible:outline-brand',
    },
    emerald: {
        btn: 'enabled:hover:border-emerald-400/55 enabled:hover:text-white enabled:hover:shadow-[0_0_22px_-6px_rgba(16,185,129,0.7)]',
        bar: 'bg-[linear-gradient(110deg,#059669,#34d399)]',
        ring: 'focus-visible:outline-emerald-400',
    },
    violet: {
        btn: 'enabled:hover:border-violet-400/55 enabled:hover:text-white enabled:hover:shadow-[0_0_22px_-6px_rgba(139,92,246,0.7)]',
        bar: 'bg-[linear-gradient(110deg,#7c3aed,#a78bfa)]',
        ring: 'focus-visible:outline-violet-400',
    },
};
const accent = computed(() => ACCENTS[props.accent] ?? ACCENTS.brand);

const viewport = ref(null);
const grabbing = ref(false);

// Trạng thái cuộn (đo lại mỗi lần scroll/resize).
const sLeft = ref(0);
const sWidth = ref(0);
const cWidth = ref(0);

const overflowing = computed(() => sWidth.value - cWidth.value > 4);
const canPrev = computed(() => sLeft.value > 4);
const canNext = computed(() => sLeft.value < sWidth.value - cWidth.value - 4);

const thumbWidth = computed(() => {
    if (sWidth.value <= 0) {
        return 100;
    }
    return Math.max(14, Math.min(100, (cWidth.value / sWidth.value) * 100));
});
const thumbLeft = computed(() => {
    const max = sWidth.value - cWidth.value;
    if (max <= 0) {
        return 0;
    }
    return (sLeft.value / max) * (100 - thumbWidth.value);
});

function measure() {
    const el = viewport.value;
    if (!el) {
        return;
    }
    sLeft.value = el.scrollLeft;
    sWidth.value = el.scrollWidth;
    cWidth.value = el.clientWidth;
}

function scrollByPage(dir) {
    const el = viewport.value;
    if (!el) {
        return;
    }
    // Dịch ~một khung hiển thị, chừa lại một thẻ để giữ ngữ cảnh.
    const step = Math.max(el.clientWidth * 0.8, 240);
    el.scrollBy({ left: dir * step, behavior: 'smooth' });
}

/* ---- Kéo bằng chuột (cảm ứng để trình duyệt tự cuộn) ---- */
const DRAG_THRESHOLD = 6;
let startX = 0;
let startScroll = 0;
let dragDist = 0;
let pointerActive = false;

function onPointerDown(e) {
    if (e.pointerType !== 'mouse' || e.button !== 0) {
        return;
    }
    const el = viewport.value;
    if (!el) {
        return;
    }
    // Chưa bắt con trỏ vội: nếu chỉ là click thì để event tới thẳng thẻ bên trong.
    // setPointerCapture trên viewport sẽ "đổi đích" click sang viewport ⇒ nuốt
    // mất @click mở modal của thẻ. Chỉ bắt khi đã chắc chắn là thao tác kéo.
    pointerActive = true;
    grabbing.value = false;
    dragDist = 0;
    startX = e.clientX;
    startScroll = el.scrollLeft;
}

function onPointerMove(e) {
    if (!pointerActive) {
        return;
    }
    const el = viewport.value;
    if (!el) {
        return;
    }
    const dx = e.clientX - startX;
    dragDist = Math.max(dragDist, Math.abs(dx));
    if (!grabbing.value) {
        if (dragDist <= DRAG_THRESHOLD) {
            return;
        }
        // Vượt ngưỡng ⇒ chuyển sang chế độ kéo, lúc này mới bắt con trỏ.
        grabbing.value = true;
        el.setPointerCapture?.(e.pointerId);
    }
    el.scrollLeft = startScroll - dx;
}

function onPointerUp(e) {
    if (!pointerActive) {
        return;
    }
    pointerActive = false;
    if (grabbing.value) {
        grabbing.value = false;
        viewport.value?.releasePointerCapture?.(e.pointerId);
    }
}

// Vừa kéo thì nuốt click để không mở modal nhầm.
function onClickCapture(e) {
    if (dragDist > 8) {
        e.preventDefault();
        e.stopPropagation();
        dragDist = 0;
    }
}

let resizeObserver = null;

onMounted(() => {
    measure();
    if (typeof ResizeObserver !== 'undefined' && viewport.value) {
        resizeObserver = new ResizeObserver(() => measure());
        resizeObserver.observe(viewport.value);
    }
    window.addEventListener('resize', measure, { passive: true });
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    window.removeEventListener('resize', measure);
});

watch(
    () => props.resetKey,
    () => {
        nextTick(() => {
            if (viewport.value) {
                viewport.value.scrollLeft = 0;
            }
            measure();
        });
    },
);

watch(
    () => props.projects.length,
    () => nextTick(measure),
);
</script>

<template>
  <div class="cn-slider relative">
    <!-- Khung cuộn -->
    <div
      ref="viewport"
      class="cn-slider__viewport flex gap-5 overflow-x-auto overscroll-x-contain scroll-smooth pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      :class="[
        overflowing ? 'snap-x snap-proximity md:cursor-grab' : '',
        grabbing ? 'cursor-grabbing select-none [&_*]:!cursor-grabbing' : '',
        accent.ring,
      ]"
      tabindex="0"
      role="group"
      aria-label="Băng chuyền dự án — kéo ngang để xem thêm"
      @scroll.passive="measure"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
      @click.capture="onClickCapture"
    >
      <div
        v-for="project in projects"
        :key="project.id"
        class="snap-start shrink-0 basis-[85%] sm:basis-[calc(50%-0.625rem)] lg:basis-[calc(33.333%-0.834rem)]"
      >
        <ProjectShowcaseCard :project="project" />
      </div>
    </div>

    <!-- Vệt mờ mép trái/phải gợi ý còn nội dung -->
    <div
      class="pointer-events-none absolute inset-y-0 left-0 w-10 bg-gradient-to-r from-[#05060c] to-transparent transition-opacity duration-300"
      :class="canPrev ? 'opacity-100' : 'opacity-0'"
      aria-hidden="true"
    />
    <div
      class="pointer-events-none absolute inset-y-0 right-0 w-12 bg-gradient-to-l from-[#05060c] to-transparent transition-opacity duration-300"
      :class="canNext ? 'opacity-100' : 'opacity-0'"
      aria-hidden="true"
    />

    <!-- Điều khiển: lùi · thanh tiến độ · tới -->
    <div
      v-if="overflowing"
      class="mt-5 flex items-center gap-4"
    >
      <button
        type="button"
        class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-white/15 bg-white/5 text-white/70 backdrop-blur transition will-change-transform disabled:cursor-not-allowed disabled:opacity-30"
        :class="accent.btn"
        :disabled="!canPrev"
        aria-label="Lùi"
        @click="scrollByPage(-1)"
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

      <div class="group relative h-1.5 flex-1 overflow-hidden rounded-full bg-white/10">
        <div
          class="absolute inset-y-0 rounded-full transition-[left,width] duration-150 ease-out"
          :class="accent.bar"
          :style="{ width: `${thumbWidth}%`, left: `${thumbLeft}%` }"
        />
      </div>

      <span class="hidden shrink-0 items-center gap-1.5 font-mono text-[10px] uppercase tracking-wider text-white/35 sm:inline-flex">
        <svg
          width="13"
          height="13"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        ><path d="M9 6 3 12l6 6M15 6l6 6-6 6" /></svg>
        Kéo để xem
      </span>

      <button
        type="button"
        class="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-white/15 bg-white/5 text-white/70 backdrop-blur transition will-change-transform disabled:cursor-not-allowed disabled:opacity-30"
        :class="accent.btn"
        :disabled="!canNext"
        aria-label="Tới"
        @click="scrollByPage(1)"
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
    </div>
  </div>
</template>

<style scoped>
.cn-slider__viewport {
    scroll-padding-left: 0.25rem;
}

/* Card bên trong giãn đều chiều cao theo thẻ cao nhất trong khung. */
.cn-slider__viewport > * {
    display: flex;
}
.cn-slider__viewport > * > * {
    width: 100%;
}
</style>
