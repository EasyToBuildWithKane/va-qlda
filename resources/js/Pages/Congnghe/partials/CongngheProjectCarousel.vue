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
const canPrev = computed(() => index.value > 0);
const canNext = computed(() => index.value < count.value - 1);

const { target, shown } = useInView({ threshold: 0.25, once: false });

function go(to) {
    const clamped = Math.max(0, Math.min(count.value - 1, to));
    if (clamped === index.value) {
        return;
    }
    dir.value = clamped > index.value ? 1 : -1;
    index.value = clamped;
}
function prev() {
    go(index.value - 1);
}
function next() {
    go(index.value + 1);
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
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

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
    class="relative"
    :class="accent.ring"
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

    <!-- Mũi tên hai bên -->
    <template v-if="count > 1">
      <button
        type="button"
        class="cn-pc-arrow left-1 sm:-left-1 lg:-left-4"
        :class="accent.arrow"
        :disabled="!canPrev"
        aria-label="Dự án trước"
        @click="prev"
      >
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        ><path d="M15 6l-6 6 6 6" /></svg>
      </button>
      <button
        type="button"
        class="cn-pc-arrow right-1 sm:-right-1 lg:-right-4"
        :class="accent.arrow"
        :disabled="!canNext"
        aria-label="Dự án sau"
        @click="next"
      >
        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        ><path d="M9 6l6 6-6 6" /></svg>
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
          @click="go(i)"
        />
      </div>
      <span class="hidden items-center gap-1.5 font-mono text-[10px] uppercase tracking-wider text-white/35 sm:inline-flex">
        <svg
          width="13"
          height="13"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
        ><path d="M9 6 3 12l6 6M15 6l6 6-6 6" /></svg>
        ← → để chuyển
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
    height: 2.75rem;
    width: 2.75rem;
    transform: translateY(-50%);
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.15);
    background: rgba(10, 12, 22, 0.78);
    color: rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(8px);
    transition: border-color 0.3s, color 0.3s, box-shadow 0.3s, opacity 0.3s;
}

.cn-pc-arrow:disabled {
    opacity: 0;
    pointer-events: none;
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
