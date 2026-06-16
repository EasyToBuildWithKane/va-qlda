<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CongngheProjectShowcase from './CongngheProjectShowcase.vue';
import { activeCongngheProject } from './useCongngheProjectModal.js';
import { useInView } from './motion.js';

/**
 * Băng chuyền dự án full-width: mỗi lần hiển thị TRỌN MỘT dự án (dải showcase),
 * dùng mũi tên hai bên / chấm điều hướng / phím ← → để chuyển sang dự án khác.
 * Chỉ mount slide đang xem (nhẹ); chuyển slide bằng hiệu ứng trượt + mờ.
 */
const props = defineProps({
    projects: { type: Array, default: () => [] },
    accent: { type: String, default: 'brand' },
    // Đổi giá trị ⇒ quay về slide đầu (vd. khi đổi giai đoạn vòng đời).
    resetKey: { type: [String, Number], default: '' },
});

const ACCENTS = {
    brand: {
        arrow: 'enabled:hover:border-brand/60 enabled:hover:text-white enabled:hover:shadow-[0_0_26px_-6px_rgba(255,77,141,0.8)]',
        dot: 'bg-[linear-gradient(110deg,#9A0036,#ff4d8d)]',
        ring: 'focus-visible:outline-brand',
    },
    emerald: {
        arrow: 'enabled:hover:border-emerald-400/60 enabled:hover:text-white enabled:hover:shadow-[0_0_26px_-6px_rgba(16,185,129,0.75)]',
        dot: 'bg-[linear-gradient(110deg,#059669,#34d399)]',
        ring: 'focus-visible:outline-emerald-400',
    },
    violet: {
        arrow: 'enabled:hover:border-violet-400/60 enabled:hover:text-white enabled:hover:shadow-[0_0_26px_-6px_rgba(139,92,246,0.75)]',
        dot: 'bg-[linear-gradient(110deg,#7c3aed,#a78bfa)]',
        ring: 'focus-visible:outline-violet-400',
    },
};
const accent = computed(() => ACCENTS[props.accent] ?? ACCENTS.brand);

const index = ref(0);
const dir = ref(1);
const count = computed(() => props.projects.length);
const current = computed(() => props.projects[index.value] ?? null);
const canNavigate = computed(() => count.value > 1);

const { target, shown } = useInView({ threshold: 0.25, once: false });

const AUTO_INTERVAL_MS = 6500;
const userPaused = ref(false);
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

function syncAutoPlay() {
    clearAutoTimer();
    if (prefersReducedMotion() || count.value < 2 || !shown.value || activeCongngheProject.value) {
        return;
    }
    autoTimer = setInterval(() => {
        if (userPaused.value || activeCongngheProject.value || !shown.value || count.value < 2) {
            return;
        }
        go((index.value + 1) % count.value);
    }, AUTO_INTERVAL_MS);
}

function pauseAutoAfterManualNav() {
    userPaused.value = true;
    if (manualPauseTimer) {
        clearTimeout(manualPauseTimer);
    }
    manualPauseTimer = setTimeout(() => {
        userPaused.value = false;
    }, AUTO_INTERVAL_MS * 1.25);
}

function go(to) {
    const clamped = Math.max(0, Math.min(count.value - 1, to));
    if (clamped === index.value) {
        return;
    }
    dir.value = clamped > index.value ? 1 : -1;
    index.value = clamped;
}
function prev() {
    if (count.value < 2) {
        return;
    }
    go((index.value - 1 + count.value) % count.value);
    pauseAutoAfterManualNav();
}
function next() {
    if (count.value < 2) {
        return;
    }
    go((index.value + 1) % count.value);
    pauseAutoAfterManualNav();
}

function onKeydown(e) {
    // Gallery (khi đang rê chuột) chặn ở capture-phase ⇒ event không tới đây.
    if (e.defaultPrevented || activeCongngheProject.value) {
        return;
    }
    if (!shown.value || count.value < 2) {
        return;
    }
    const tag = e.target?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.target?.isContentEditable) {
        return;
    }
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        prev();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        next();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    clearAutoTimer();
    if (manualPauseTimer) {
        clearTimeout(manualPauseTimer);
    }
});

watch([shown, count, () => activeCongngheProject.value], syncAutoPlay, { immediate: true });

watch(
    () => props.resetKey,
    () => {
        dir.value = 1;
        index.value = 0;
    },
);
watch(count, (n) => {
    if (index.value > n - 1) {
        index.value = Math.max(0, n - 1);
    }
});
</script>

<template>
  <div
    ref="target"
    class="cn-pc-root relative px-11 sm:px-16 lg:px-[4.75rem]"
    :class="accent.ring"
    @mouseenter="userPaused = true"
    @mouseleave="userPaused = false"
    @focusin="userPaused = true"
    @focusout="(e) => { if (!e.currentTarget.contains(e.relatedTarget)) userPaused = false; }"
  >
    <!-- Khung slide: giữ bóng đổ (không cắt); cuộn ngang đã bị chặn ở root trang -->
    <div class="cn-pc-stage relative">
      <Transition :name="dir === 1 ? 'cn-pc-next' : 'cn-pc-prev'">
        <CongngheProjectShowcase
          v-if="current"
          :key="current.id"
          :project="current"
          :index="index"
        />
      </Transition>
    </div>

    <!-- Mũi tên hai bên — đặt trong gutter, pulse gợi ý vuốt/chuyển -->
    <template v-if="canNavigate">
      <button
        type="button"
        class="cn-pc-arrow cn-pc-arrow--left left-0 sm:left-1"
        :class="accent.arrow"
        aria-label="Dự án trước"
        @click="prev"
      >
        <span
          class="cn-pc-arrow__ring"
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
        <span class="cn-pc-arrow__hint">Trước</span>
      </button>
      <button
        type="button"
        class="cn-pc-arrow cn-pc-arrow--right right-0 sm:right-1"
        :class="accent.arrow"
        aria-label="Dự án sau"
        @click="next"
      >
        <span
          class="cn-pc-arrow__ring"
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
        <span class="cn-pc-arrow__hint">Tiếp</span>
      </button>
    </template>

    <!-- Điều khiển: đếm · chấm · gợi ý phím -->
    <div
      v-if="count > 1"
      class="mt-7 flex flex-wrap items-center justify-center gap-x-5 gap-y-3"
    >
      <span class="font-mono text-[11px] tabular-nums text-white/45">{{ index + 1 }} / {{ count }}</span>
      <div
        class="flex items-center gap-1.5"
        role="tablist"
        aria-label="Chọn dự án"
      >
        <button
          v-for="(p, i) in projects"
          :key="p.id"
          type="button"
          role="tab"
          :aria-selected="i === index"
          :aria-label="`Dự án ${i + 1}`"
          class="h-1.5 rounded-full transition-all duration-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
          :class="i === index ? [accent.dot, 'w-7'] : 'w-1.5 bg-white/20 hover:bg-white/40'"
          @click="() => { go(i); pauseAutoAfterManualNav(); }"
        />
      </div>
      <span class="hidden items-center gap-1.5 font-mono text-[10px] uppercase tracking-wider text-white/35 sm:inline-flex">
        <span
          class="inline-block h-1.5 w-1.5 rounded-full bg-white/50 animate-pulse"
          aria-hidden="true"
        />
        Tự chuyển · ← → hoặc bấm mũi tên
      </span>
    </div>
  </div>
</template>

<style scoped>
.cn-pc-arrow {
    position: absolute;
    top: 42%;
    z-index: 20;
    display: grid;
    place-items: center;
    height: 3.25rem;
    width: 3.25rem;
    transform: translateY(-50%);
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.22);
    background: rgba(10, 12, 22, 0.88);
    color: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(10px);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.06) inset,
        0 8px 28px -8px rgba(0, 0, 0, 0.65);
    transition:
        border-color 0.3s,
        color 0.3s,
        box-shadow 0.3s,
        transform 0.25s ease;
}

.cn-pc-arrow__ring {
    position: absolute;
    inset: -5px;
    border-radius: inherit;
    border: 1px solid rgba(255, 255, 255, 0.12);
    opacity: 0.85;
    animation: cn-pc-arrow-ring 2.2s ease-out infinite;
    pointer-events: none;
}

.cn-pc-arrow__hint {
    position: absolute;
    top: calc(100% + 0.45rem);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.42);
    white-space: nowrap;
    opacity: 0;
    transform: translateY(-4px);
    transition: opacity 0.25s, transform 0.25s;
    pointer-events: none;
}

.cn-pc-arrow:hover .cn-pc-arrow__hint,
.cn-pc-arrow:focus-visible .cn-pc-arrow__hint {
    opacity: 1;
    transform: translateY(0);
}

.cn-pc-arrow--left {
    animation: cn-pc-nudge-left 2.8s ease-in-out infinite;
}

.cn-pc-arrow--right {
    animation: cn-pc-nudge-right 2.8s ease-in-out infinite;
}

.cn-pc-arrow--left:hover,
.cn-pc-arrow--right:hover,
.cn-pc-arrow--left:focus-visible,
.cn-pc-arrow--right:focus-visible {
    animation: none;
    transform: translateY(-50%) scale(1.06);
}

@keyframes cn-pc-arrow-ring {
    0% {
        transform: scale(1);
        opacity: 0.75;
    }
    70% {
        transform: scale(1.22);
        opacity: 0;
    }
    100% {
        transform: scale(1.22);
        opacity: 0;
    }
}

@keyframes cn-pc-nudge-left {
    0%,
    100% {
        transform: translateY(-50%) translateX(0);
    }
    50% {
        transform: translateY(-50%) translateX(-5px);
    }
}

@keyframes cn-pc-nudge-right {
    0%,
    100% {
        transform: translateY(-50%) translateX(0);
    }
    50% {
        transform: translateY(-50%) translateX(5px);
    }
}

/* Hiệu ứng trượt + mờ giữa các slide; slide rời được đặt absolute để không đẩy layout */
.cn-pc-next-enter-active,
.cn-pc-prev-enter-active,
.cn-pc-next-leave-active,
.cn-pc-prev-leave-active {
    transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.45s ease;
}

.cn-pc-next-leave-active,
.cn-pc-prev-leave-active {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
}

.cn-pc-next-enter-from {
    transform: translateX(54px);
    opacity: 0;
}
.cn-pc-next-leave-to {
    transform: translateX(-54px);
    opacity: 0;
}
.cn-pc-prev-enter-from {
    transform: translateX(-54px);
    opacity: 0;
}
.cn-pc-prev-leave-to {
    transform: translateX(54px);
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .cn-pc-arrow--left,
    .cn-pc-arrow--right,
    .cn-pc-arrow__ring {
        animation: none;
    }

    .cn-pc-next-enter-active,
    .cn-pc-prev-enter-active,
    .cn-pc-next-leave-active,
    .cn-pc-prev-leave-active {
        transition: opacity 0.2s ease;
    }
    .cn-pc-next-enter-from,
    .cn-pc-prev-enter-from,
    .cn-pc-next-leave-to,
    .cn-pc-prev-leave-to {
        transform: none;
    }
}
</style>
