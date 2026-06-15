<script setup>
import {
    computed, onBeforeUnmount, onMounted, ref, watch,
} from 'vue';
import CongngheMascotAnimated from './CongngheMascotAnimated.vue';
import { congngheBrand } from './congngheBrand.js';
import { prefersReducedMotionNow } from './motion.js';
import { useCongngheSectionSpy } from './useCongngheSectionSpy.js';
import { useCongngheAssistantDock } from './useCongngheAssistantDock.js';

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
            'Sơ đồ tổ chức — vuốt di chuyển, +/- để phóng to trên điện thoại.',
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

const {
    isDragging,
    dockEnabled,
    dockStyle,
    onPointerDown,
    onPointerMove,
    finishPointer,
} = useCongngheAssistantDock();

const asideStyle = computed(() => {
    if (dockEnabled.value) {
        return dockStyle();
    }
    return {};
});

function onFabPointerDown(e) {
    onPointerDown(e);
}

function onFabPointerMove(e) {
    onPointerMove(e);
}

function onFabPointerUp(e) {
    const dragged = finishPointer(e);
    if (!dragged) {
        toggle();
    }
}

function onFabClick() {
    if (!dockEnabled.value) {
        toggle();
    }
}
</script>

<template>
  <aside
    class="cn-assistant fixed z-[45] flex max-w-[min(100vw-1rem,20rem)] flex-col items-end gap-2 pointer-events-none lg:max-w-none"
    :class="{ 'cn-assistant--dragging': isDragging }"
    :style="asideStyle"
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
        class="pointer-events-auto cn-assistant-bubble relative max-w-[min(calc(100vw-2.5rem),17rem)] rounded-2xl border border-white/15 bg-[#0c0e18]/95 px-3 py-2.5 shadow-[0_16px_48px_-12px_rgba(154,0,54,0.45)] backdrop-blur-xl sm:max-w-[20rem] sm:px-3.5 sm:py-3 md:max-w-[22rem]"
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
          class="mt-1 max-h-[40vh] overflow-y-auto text-[12.5px] leading-snug text-white/90 sm:mt-1.5 sm:max-h-none sm:text-sm"
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

    <div
      class="pointer-events-auto flex items-center gap-1 sm:gap-1.5"
      :class="dockEnabled ? 'cn-assistant-fab-row--dock' : ''"
    >
      <button
        v-if="dockEnabled"
        type="button"
        class="cn-assistant-grip grid h-10 w-7 shrink-0 touch-none items-center justify-center rounded-l-xl border border-white/10 border-r-0 bg-[#0c0e18]/75 text-white/40 backdrop-blur-md active:bg-white/10"
        aria-label="Kéo trợ lý lên hoặc xuống"
        @pointerdown="onFabPointerDown"
        @pointermove="onFabPointerMove"
        @pointerup="onFabPointerUp"
        @pointercancel="onFabPointerUp"
      >
        <span
          class="flex flex-col gap-0.5"
          aria-hidden="true"
        >
          <span class="h-0.5 w-3 rounded-full bg-current opacity-70" />
          <span class="h-0.5 w-3 rounded-full bg-current opacity-70" />
          <span class="h-0.5 w-3 rounded-full bg-current opacity-70" />
        </span>
      </button>

      <button
        type="button"
        class="cn-assistant-fab group relative overflow-visible border-0 bg-transparent p-0 shadow-none transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/50 focus-visible:ring-offset-2 focus-visible:ring-offset-[#05060c]"
        :class="dockEnabled ? 'touch-none' : ''"
        :aria-expanded="expanded"
        :aria-label="expanded ? 'Thu gọn trợ lý' : 'Mở trợ lý VAS'"
        @click="onFabClick"
        @pointerdown="dockEnabled ? onFabPointerDown($event) : undefined"
        @pointermove="dockEnabled ? onFabPointerMove($event) : undefined"
        @pointerup="dockEnabled ? onFabPointerUp($event) : undefined"
        @pointercancel="dockEnabled ? onFabPointerUp($event) : undefined"
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
    </div>

    <p
      v-if="dockEnabled && !isDragging"
      class="pointer-events-none pr-1 text-[9px] text-white/35"
    >
      Kéo thanh ⋮ để đổi vị trí
    </p>
  </aside>
</template>

<style scoped>
.cn-assistant {
    right: max(0.5rem, env(safe-area-inset-right, 0px));
    transition: bottom 0.15s ease-out;
}

.cn-assistant--dragging {
    transition: none;
}

@media (min-width: 640px) {
    .cn-assistant {
        right: max(1rem, env(safe-area-inset-right, 0px));
    }
}

@media (min-width: 1024px) {
    .cn-assistant {
        right: max(1.5rem, env(safe-area-inset-right, 0px));
        bottom: max(1.5rem, env(safe-area-inset-bottom, 0px)) !important;
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

.cn-assistant-fab-row--dock {
    filter: drop-shadow(0 8px 24px rgba(0, 0, 0, 0.45));
}

@media (prefers-reduced-motion: reduce) {
    .cn-assistant-bubble,
    .cn-assistant-bubble p,
    .cn-assistant {
        transition: none !important;
        animation: none !important;
    }
}
</style>
