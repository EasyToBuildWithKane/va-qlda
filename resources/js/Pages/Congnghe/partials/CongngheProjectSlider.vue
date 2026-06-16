<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ProjectShowcaseCard from './ProjectShowcaseCard.vue';
import { activeCongngheProject } from './useCongngheProjectModal.js';
import { useInView } from './motion.js';

/**
 * Băng chuyền dự án dùng chung cho "Vòng đời sản phẩm số" và "Hệ sinh thái sản
 * phẩm": một hàng, 3 thẻ/khung trên desktop (2 trên tablet, ~1.1 trên mobile),
 * kéo ngang bằng chuột hoặc vuốt cảm ứng. Mũi tên hai bên khung + thanh tiến độ;
 * tự cuộn khi không tương tác. Khi vừa kéo (>8px) sẽ chặn click mở modal.
 */
const props = defineProps({
    projects: { type: Array, default: () => [] },
    accent: { type: String, default: 'brand' },
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

const { target: inViewTarget, shown: inView } = useInView({ threshold: 0.15, once: false });

const viewport = ref(null);
const grabbing = ref(false);
const userPaused = ref(false);

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

const scrollProgress = computed(() => {
    const max = sWidth.value - cWidth.value;
    if (max <= 0) {
        return 0;
    }
    return Math.round((sLeft.value / max) * 100);
});

const AUTO_INTERVAL_MS = 5500;
let autoTimer = null;
let manualPauseTimer = null;

function prefersReducedMotion() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function clearAutoTimer() {
    if (autoTimer) {
        clearInterval(autoTimer);
        autoTimer = null;
    }
}

function pauseAutoAfterManual() {
    userPaused.value = true;
    if (manualPauseTimer) {
        clearTimeout(manualPauseTimer);
    }
    manualPauseTimer = setTimeout(() => {
        userPaused.value = false;
    }, AUTO_INTERVAL_MS * 1.3);
}

function syncAutoPlay() {
    clearAutoTimer();
    if (prefersReducedMotion() || !overflowing.value || !inView.value || activeCongngheProject.value) {
        return;
    }
    autoTimer = setInterval(() => {
        if (userPaused.value || grabbing.value || !inView.value || activeCongngheProject.value) {
            return;
        }
        autoAdvance();
    }, AUTO_INTERVAL_MS);
}

function autoAdvance() {
    const el = viewport.value;
    if (!el) {
        return;
    }
    if (canNext.value) {
        scrollByPage(1, false);
    } else {
        el.scrollTo({ left: 0, behavior: 'smooth' });
    }
}

function measure() {
    const el = viewport.value;
    if (!el) {
        return;
    }
    sLeft.value = el.scrollLeft;
    sWidth.value = el.scrollWidth;
    cWidth.value = el.clientWidth;
}

function scrollByPage(dir, fromUser = true) {
    const el = viewport.value;
    if (!el) {
        return;
    }
    const max = el.scrollWidth - el.clientWidth;
    if (dir < 0 && !canPrev.value) {
        el.scrollTo({ left: max, behavior: 'smooth' });
        if (fromUser) {
            pauseAutoAfterManual();
        }
        return;
    }
    if (dir > 0 && !canNext.value) {
        el.scrollTo({ left: 0, behavior: 'smooth' });
        if (fromUser) {
            pauseAutoAfterManual();
        }
        return;
    }
    const step = Math.max(el.clientWidth * 0.78, 240);
    el.scrollBy({ left: dir * step, behavior: 'smooth' });
    if (fromUser) {
        pauseAutoAfterManual();
    }
}

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
    pointerActive = true;
    grabbing.value = false;
    dragDist = 0;
    startX = e.clientX;
    startScroll = el.scrollLeft;
    userPaused.value = true;
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
        pauseAutoAfterManual();
    } else {
        userPaused.value = false;
    }
}

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
    clearAutoTimer();
    if (manualPauseTimer) {
        clearTimeout(manualPauseTimer);
    }
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

watch([inView, overflowing, () => activeCongngheProject.value], syncAutoPlay, { immediate: true });
</script>

<template>
  <div
    ref="inViewTarget"
    class="cn-slider relative"
    @mouseenter="userPaused = true"
    @mouseleave="userPaused = false"
    @focusin="userPaused = true"
    @focusout="(e) => { if (!e.currentTarget.contains(e.relatedTarget)) userPaused = false; }"
  >
    <!-- Gợi ý điều hướng (trên khung thẻ) -->
    <p
      v-if="overflowing"
      class="mb-4 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-center font-mono text-[10px] uppercase tracking-[0.14em] text-white/45"
    >
      <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/[0.04] px-2.5 py-1">
        <span
          class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-brand/90"
          aria-hidden="true"
        />
        Tự chuyển dự án
      </span>
      <span class="text-white/30">·</span>
      <span>Bấm mũi tên hai bên hoặc kéo ngang</span>
    </p>

    <!-- Khung thẻ + mũi tên overlay -->
    <div class="relative px-11 sm:px-14 lg:px-[4.25rem]">
      <template v-if="overflowing">
        <button
          type="button"
          class="cn-slider-arrow cn-slider-arrow--left left-0 sm:left-0.5"
          :class="accent.btn"
          aria-label="Xem dự án phía trước"
          @click="scrollByPage(-1)"
        >
          <span
            class="cn-slider-arrow__ring"
            aria-hidden="true"
          />
          <svg
            width="22"
            height="22"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.25"
          ><path d="M15 6l-6 6 6 6" /></svg>
          <span class="cn-slider-arrow__label">Trước</span>
        </button>
        <button
          type="button"
          class="cn-slider-arrow cn-slider-arrow--right right-0 sm:right-0.5"
          :class="accent.btn"
          aria-label="Xem dự án tiếp theo"
          @click="scrollByPage(1)"
        >
          <span
            class="cn-slider-arrow__ring"
            aria-hidden="true"
          />
          <svg
            width="22"
            height="22"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.25"
          ><path d="M9 6l6 6-6 6" /></svg>
          <span class="cn-slider-arrow__label">Tiếp</span>
        </button>
      </template>

      <div
        ref="viewport"
        class="cn-slider__viewport flex gap-5 overflow-x-auto overscroll-x-contain scroll-smooth rounded-xl border-2 border-black/95 bg-black/25 pb-1 pl-1 pr-1 pt-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        :class="[
          overflowing ? 'snap-x snap-proximity md:cursor-grab' : '',
          grabbing ? 'cursor-grabbing select-none [&_*]:!cursor-grabbing' : '',
          accent.ring,
        ]"
        tabindex="0"
        role="group"
        aria-label="Băng chuyền dự án — kéo ngang hoặc dùng mũi tên hai bên"
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
          <ProjectShowcaseCard
            :project="project"
            dark-outline
          />
        </div>
      </div>

      <div
        class="pointer-events-none absolute inset-y-1 left-11 w-8 rounded-l-xl bg-gradient-to-r from-[#05060c] to-transparent transition-opacity duration-300 sm:left-14 lg:left-[4.25rem]"
        :class="canPrev ? 'opacity-90' : 'opacity-0'"
        aria-hidden="true"
      />
      <div
        class="pointer-events-none absolute inset-y-1 right-11 w-10 rounded-r-xl bg-gradient-to-l from-[#05060c] to-transparent transition-opacity duration-300 sm:right-14 lg:right-[4.25rem]"
        :class="canNext ? 'opacity-90' : 'opacity-0'"
        aria-hidden="true"
      />
    </div>

    <div
      v-if="overflowing"
      class="mx-auto mt-5 max-w-xl px-2"
    >
      <div class="relative h-1.5 overflow-hidden rounded-full bg-white/10">
        <div
          class="absolute inset-y-0 rounded-full transition-[left,width] duration-300 ease-out"
          :class="accent.bar"
          :style="{ width: `${thumbWidth}%`, left: `${thumbLeft}%` }"
        />
      </div>
      <p class="mt-2 text-center font-mono text-[10px] tabular-nums text-white/35">
        {{ scrollProgress }}% · cuộn để xem thêm dự án
      </p>
    </div>
  </div>
</template>

<style scoped>
.cn-slider__viewport {
    scroll-padding-left: 0.25rem;
}

.cn-slider__viewport > * {
    display: flex;
}
.cn-slider__viewport > * > * {
    width: 100%;
}

.cn-slider-arrow {
    position: absolute;
    top: 50%;
    z-index: 25;
    display: grid;
    place-items: center;
    height: 3.25rem;
    width: 3.25rem;
    transform: translateY(-50%);
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.24);
    background: rgba(8, 10, 18, 0.92);
    color: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    box-shadow:
        0 0 0 1px rgba(0, 0, 0, 0.85) inset,
        0 10px 32px -10px rgba(0, 0, 0, 0.75);
    transition:
        border-color 0.3s,
        color 0.3s,
        box-shadow 0.3s,
        transform 0.25s ease;
}

.cn-slider-arrow__ring {
    position: absolute;
    inset: -6px;
    border-radius: inherit;
    border: 1px solid rgba(255, 255, 255, 0.14);
    animation: cn-slider-arrow-ring 2.1s ease-out infinite;
    pointer-events: none;
}

.cn-slider-arrow__label {
    position: absolute;
    top: calc(100% + 0.4rem);
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.5);
    white-space: nowrap;
    pointer-events: none;
}

.cn-slider-arrow--left {
    animation: cn-slider-nudge-left 2.6s ease-in-out infinite;
}

.cn-slider-arrow--right {
    animation: cn-slider-nudge-right 2.6s ease-in-out infinite;
}

.cn-slider-arrow:hover,
.cn-slider-arrow:focus-visible {
    animation: none;
    transform: translateY(-50%) scale(1.07);
}

@keyframes cn-slider-arrow-ring {
    0% {
        transform: scale(1);
        opacity: 0.8;
    }
    70% {
        transform: scale(1.24);
        opacity: 0;
    }
    100% {
        transform: scale(1.24);
        opacity: 0;
    }
}

@keyframes cn-slider-nudge-left {
    0%,
    100% {
        transform: translateY(-50%) translateX(0);
    }
    50% {
        transform: translateY(-50%) translateX(-6px);
    }
}

@keyframes cn-slider-nudge-right {
    0%,
    100% {
        transform: translateY(-50%) translateX(0);
    }
    50% {
        transform: translateY(-50%) translateX(6px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cn-slider-arrow--left,
    .cn-slider-arrow--right,
    .cn-slider-arrow__ring {
        animation: none;
    }
}
</style>
