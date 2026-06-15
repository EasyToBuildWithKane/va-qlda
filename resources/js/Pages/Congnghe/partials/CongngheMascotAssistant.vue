<script setup>
import {
    computed, onBeforeUnmount, onMounted, ref, watch,
} from 'vue';
import CongngheMascotAnimated from './CongngheMascotAnimated.vue';
import { congngheBrand } from './congngheBrand.js';
import { prefersReducedMotionNow } from './motion.js';
import { useCongngheSectionSpy } from './useCongngheSectionSpy.js';

const { activeId } = useCongngheSectionSpy();

const expanded = ref(true);
const tipIndex = ref(0);
let idleTimer = null;

const sectionTips = {
    top: {
        src: congngheBrand.mascotWave,
        lines: [
            'Xin chào! Mình là trợ lý VAS — cùng bạn khám phá Phòng Công Nghệ nhé.',
            'Cuộn xuống hoặc bấm «Giới thiệu» để bắt đầu hành trình.',
        ],
    },
    'gioi-thieu': {
        src: congngheBrand.mascotVaJacket,
        lines: [
            'Sứ mệnh, tầm nhìn và giá trị — kim chỉ nam cho mọi sản phẩm chúng mình ship.',
            'Mỗi quy trình đều hướng tới trải nghiệm người học và đội ngũ vận hành.',
        ],
    },
    'thanh-tuu': {
        src: congngheBrand.mascotHoodie,
        lines: [
            'Các con số này lấy trực tiếp từ QLDA — cập nhật theo dữ liệu thật.',
            'Vuốt ngang trên điện thoại để xem đủ chỉ số nhé.',
        ],
    },
    'san-pham': {
        src: congngheBrand.mascotWave,
        lines: [
            'Hệ sinh thái sản phẩm — bấm từng thẻ để xem chi tiết dự án.',
            'Carousel hỗ trợ cảm ứng: vuốt hoặc dùng nút mũi tên.',
        ],
    },
    'cong-nghe': {
        src: congngheBrand.mascotVaJacket,
        lines: [
            'Stack công nghệ chúng mình dùng hàng ngày — Laravel, Vue, AI pipeline…',
        ],
    },
    'to-chuc': {
        src: congngheBrand.mascotHoodie,
        lines: [
            'Sơ đồ tổ chức và con người — zoom/pan trên bảng lớn, cuộn trên mobile.',
        ],
    },
    'du-an': {
        src: congngheBrand.mascotWave,
        lines: [
            'Timeline dự án theo giai đạn — theo dõi tiến độ triển khai.',
        ],
    },
    'ai-lab': {
        src: congngheBrand.mascotVaJacket,
        lines: [
            'AI Lab — thử nghiệm, đo lường và đưa AI vào sản phẩm an toàn.',
        ],
    },
    'van-hoa': {
        src: congngheBrand.mascotHoodie,
        lines: [
            'Văn hoá đội ngũ: làm sản phẩm thật, tử tế và đo lường được.',
        ],
    },
    'lo-trinh': {
        src: congngheBrand.mascotWave,
        lines: [
            'Lộ trình 2026–2027 — hướng đi chiến lược của Phòng Công Nghệ.',
            'Cảm ơn bạn đã ghé thăm! Liên hệ qua email nếu cần hỗ trợ.',
        ],
    },
};

const context = computed(() => sectionTips[activeId.value] ?? sectionTips.top);

const currentLine = computed(() => {
    const lines = context.value.lines;
    return lines[tipIndex.value % lines.length] ?? lines[0];
});

const mascotSrc = computed(() => context.value.src);

function cycleTip() {
    const lines = context.value.lines;
    if (lines.length <= 1) {
        return;
    }
    tipIndex.value = (tipIndex.value + 1) % lines.length;
}

function startIdleCycle() {
    clearInterval(idleTimer);
    if (prefersReducedMotionNow()) {
        return;
    }
    idleTimer = setInterval(() => {
        cycleTip();
    }, 7000);
}

watch(activeId, () => {
    tipIndex.value = 0;
    if (expanded.value) {
        startIdleCycle();
    }
});

watch(expanded, (on) => {
    if (on) {
        startIdleCycle();
    } else {
        clearInterval(idleTimer);
    }
});

onMounted(() => {
    if (typeof window !== 'undefined' && window.matchMedia('(max-width: 639px)').matches) {
        expanded.value = false;
    }
    startIdleCycle();
});

onBeforeUnmount(() => clearInterval(idleTimer));

function toggle() {
    expanded.value = !expanded.value;
}

const bubbleVisible = computed(() => expanded.value);
</script>

<template>
  <aside
    class="cn-assistant fixed z-40 flex flex-col items-end gap-2 pointer-events-none"
    aria-live="polite"
    aria-label="Trợ lý ảo Phòng Công Nghệ"
  >
    <transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-y-2 opacity-0 scale-95"
      leave-active-class="transition duration-200 ease-in"
      leave-to-class="translate-y-2 opacity-0 scale-95"
    >
      <div
        v-if="bubbleVisible"
        class="pointer-events-auto cn-assistant-bubble relative max-w-[min(calc(100vw-2rem),18rem)] rounded-2xl border border-white/15 bg-[#0c0e18]/95 px-3.5 py-3 shadow-[0_16px_48px_-12px_rgba(154,0,54,0.45)] backdrop-blur-xl sm:max-w-[20rem] md:max-w-[22rem]"
      >
        <span
          class="absolute -bottom-1.5 right-8 h-3 w-3 rotate-45 border-b border-r border-white/15 bg-[#0c0e18]/95"
          aria-hidden="true"
        />
        <p class="font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-cyan-200/60">
          Trợ lý VAS
        </p>
        <p
          :key="`${activeId}-${tipIndex}`"
          class="mt-1.5 text-[13px] leading-snug text-white/90 sm:text-sm"
          :class="prefersReducedMotionNow() ? '' : 'animate-cn-rise'"
        >
          {{ currentLine }}
        </p>
        <div
          v-if="context.lines.length > 1"
          class="mt-2 flex gap-1"
          aria-hidden="true"
        >
          <span
            v-for="(_, i) in context.lines"
            :key="i"
            class="h-1 rounded-full transition-all duration-300"
            :class="i === tipIndex % context.lines.length ? 'w-4 bg-brand' : 'w-1 bg-white/25'"
          />
        </div>
      </div>
    </transition>

    <button
      type="button"
      class="pointer-events-auto group relative flex items-end gap-2 rounded-2xl border border-white/10 bg-[#070912]/90 p-1.5 shadow-[0_12px_40px_-8px_rgba(0,0,0,0.65)] backdrop-blur-xl transition hover:border-brand/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/50 sm:rounded-3xl sm:p-2"
      :aria-expanded="expanded"
      :aria-label="expanded ? 'Thu gọn trợ lý' : 'Mở trợ lý VAS'"
      @click="toggle"
    >
      <CongngheMascotAnimated
        :src="mascotSrc"
        alt=""
        variant="assistant"
      />
      <span
        class="absolute -left-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-brand to-[#ff4d8d] text-[10px] font-bold text-white shadow-lg sm:h-6 sm:w-6 sm:text-[11px]"
        :class="prefersReducedMotionNow() ? '' : 'animate-cn-glow'"
      >
        {{ expanded ? '−' : '?' }}
      </span>
    </button>
  </aside>
</template>

<style scoped>
.cn-assistant {
    right: max(0.75rem, env(safe-area-inset-right, 0px));
    bottom: max(0.75rem, env(safe-area-inset-bottom, 0px));
}

@media (min-width: 640px) {
    .cn-assistant {
        right: max(1.25rem, env(safe-area-inset-right, 0px));
        bottom: max(1.25rem, env(safe-area-inset-bottom, 0px));
    }
}

@media (min-width: 1024px) {
    .cn-assistant {
        right: max(1.5rem, env(safe-area-inset-right, 0px));
        bottom: max(1.5rem, env(safe-area-inset-bottom, 0px));
    }
}

.cn-assistant-bubble::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    padding: 1px;
    background: linear-gradient(135deg, rgba(255, 77, 141, 0.35), rgba(56, 189, 248, 0.2), transparent 55%);
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

@media (prefers-reduced-motion: reduce) {
    .cn-assistant-bubble,
    .cn-assistant-bubble p {
        animation: none !important;
    }
}
</style>
