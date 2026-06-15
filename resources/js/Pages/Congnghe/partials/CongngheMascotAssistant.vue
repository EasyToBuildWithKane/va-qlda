<script setup>
import {
    computed, onBeforeUnmount, onMounted, ref, watch,
} from 'vue';
import CongngheMascotAnimated from './CongngheMascotAnimated.vue';
import { congngheBrand } from './congngheBrand.js';
import { prefersReducedMotionNow } from './motion.js';
import { useCongngheSectionSpy } from './useCongngheSectionSpy.js';
import { useCongngheAssistantDock } from './useCongngheAssistantDock.js';

import { congngheMascotActions } from './congngheMascotActions.js';

const props = defineProps({
    proposalPage: { type: Boolean, default: false },
});

const { activeId } = useCongngheSectionSpy();

const expanded = ref(true);
const tipIndex = ref(0);
const asideRef = ref(null);
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
            'Sơ đồ tổ chức dạng thẻ — bấm avatar để xem chi tiết liên hệ.',
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

const quickActions = computed(() => congngheMascotActions({ onProposalPage: props.proposalPage }));

function actionClass(kind) {
    if (kind === 'primary') {
        return 'border-brand/45 bg-brand/25 text-white hover:bg-brand/40';
    }
    if (kind === 'mailto') {
        return 'border-cyan-500/25 bg-cyan-500/10 text-cyan-100/90 hover:bg-cyan-500/20';
    }
    return 'border-white/10 bg-white/[0.04] text-white/80 hover:bg-white/[0.08] hover:text-white';
}

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
    hidden,
    isDragging,
    dockStyle,
    onPointerDown,
    onPointerMove,
    finishPointer,
    hideAssistant,
    showAssistant,
} = useCongngheAssistantDock(asideRef);

function onDragPointerDown(e) {
    onPointerDown(e);
}

function onDragPointerMove(e) {
    onPointerMove(e);
}

function onFabPointerUp(e) {
    const dragged = finishPointer(e);
    if (!dragged) {
        toggle();
    }
}

function onHideClick() {
    expanded.value = false;
    hideAssistant();
}
</script>

<template>
  <!-- Tab mở lại khi đã ẩn -->
  <button
    v-if="hidden"
    type="button"
    class="fixed z-[45] flex items-center gap-1.5 rounded-full border border-white/15 bg-[#0c0e18]/90 px-3 py-2 text-xs font-semibold text-white/85 shadow-lg backdrop-blur-md transition hover:border-brand/40 hover:text-white"
    style="right: max(0.75rem, env(safe-area-inset-right)); bottom: max(0.75rem, env(safe-area-inset-bottom));"
    aria-label="Hiện trợ lý VAS"
    @click="showAssistant"
  >
    <span class="h-2 w-2 rounded-full bg-brand shadow-[0_0_8px_rgba(255,77,141,0.8)]" />
    Trợ lý VAS
  </button>

  <aside
    v-else
    ref="asideRef"
    class="cn-assistant fixed z-[45] flex w-max max-w-[min(calc(100vw-1rem),20rem)] flex-col items-end gap-2 pointer-events-none sm:max-w-[22rem]"
    :class="{ 'cn-assistant--dragging': isDragging }"
    :style="dockStyle"
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
        class="pointer-events-auto cn-assistant-bubble relative max-w-[min(calc(100vw-2.5rem),17rem)] rounded-2xl border border-white/15 bg-[#0c0e18]/95 px-3 py-2.5 shadow-[0_16px_48px_-12px_rgba(154,0,54,0.45)] backdrop-blur-xl sm:max-w-[20rem] sm:px-3.5 sm:py-3"
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
          class="mt-1 max-h-[28vh] overflow-y-auto text-[12.5px] leading-snug text-white/90 sm:mt-1.5 sm:max-h-none sm:text-sm"
          :class="prefersReducedMotionNow() ? '' : 'animate-cn-rise'"
        >
          {{ currentLine }}
        </p>
        <div class="mt-3 border-t border-white/10 pt-3">
          <p class="font-mono text-[9px] font-semibold uppercase tracking-[0.14em] text-white/40">
            Bạn muốn
          </p>
          <ul class="mt-2 space-y-1.5">
            <li
              v-for="action in quickActions"
              :key="action.key"
            >
              <a
                :href="action.href"
                class="block rounded-lg border px-2.5 py-2 text-[12px] font-medium leading-snug transition sm:text-[13px]"
                :class="actionClass(action.kind)"
              >
                {{ action.label }}
              </a>
            </li>
          </ul>
        </div>
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
      class="pointer-events-auto cn-assistant-fab-row relative"
    >
      <button
        type="button"
        class="cn-assistant-fab group relative touch-none cursor-grab overflow-visible border-0 bg-transparent p-0 shadow-none transition active:cursor-grabbing focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/50 focus-visible:ring-offset-2 focus-visible:ring-offset-[#05060c]"
        :class="{ 'cursor-grabbing': isDragging }"
        :aria-expanded="expanded"
        :aria-label="expanded ? 'Thu gọn trợ lý — giữ và kéo để di chuyển' : 'Mở trợ lý VAS — giữ và kéo để di chuyển'"
        @pointerdown="onDragPointerDown"
        @pointermove="onDragPointerMove"
        @pointerup="onFabPointerUp"
        @pointercancel="onFabPointerUp"
      >
        <CongngheMascotAnimated
          :src="mascotSrc"
          alt=""
          variant="assistant"
        />
        <span
          class="absolute -left-1 bottom-0 flex h-5 w-5 items-center justify-center rounded-full bg-gradient-to-br from-brand to-[#ff4d8d] text-[10px] font-bold text-white shadow-lg sm:h-6 sm:w-6 sm:text-[11px]"
          :class="prefersReducedMotionNow() ? '' : 'animate-cn-glow'"
        >
          {{ expanded ? '−' : '?' }}
        </span>
      </button>
      <button
        type="button"
        class="absolute -right-0.5 -top-0.5 z-20 grid h-6 w-6 place-items-center rounded-full border border-white/15 bg-[#0c0e18]/95 text-white/70 shadow-md backdrop-blur-sm transition hover:border-white/25 hover:bg-[#151828] hover:text-white sm:h-7 sm:w-7"
        aria-label="Ẩn trợ lý"
        @pointerdown.stop
        @click.stop="onHideClick"
      >
        <svg
          width="14"
          height="14"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2.25"
          aria-hidden="true"
        ><path d="M18 6 6 18M6 6l12 12" /></svg>
      </button>
    </div>

    <p
      v-if="!isDragging"
      class="pointer-events-none max-w-[12rem] text-right text-[9px] leading-snug text-white/35"
    >
      Giữ linh vật để kéo · Bấm × để ẩn
    </p>
  </aside>
</template>

<style scoped>
.cn-assistant {
    transition: left 0.15s ease-out, top 0.15s ease-out;
}

.cn-assistant--dragging {
    transition: none;
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
    .cn-assistant-bubble p,
    .cn-assistant {
        transition: none !important;
        animation: none !important;
    }
}
</style>
