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

const panelOpen = ref(true);
const actionsOpen = ref(false);
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

const sectionLabel = computed(() => {
    const labels = {
        top: 'Trang chủ',
        'gioi-thieu': 'Giới thiệu',
        'thanh-tuu': 'Thành tựu',
        'san-pham': 'Sản phẩm',
        'cong-nghe': 'Công nghệ',
        'to-chuc': 'Tổ chức',
        'du-an': 'Dự án',
        'ai-lab': 'AI Lab',
        'van-hoa': 'Văn hoá',
        'lo-trinh': 'Lộ trình',
    };
    return labels[activeId.value] ?? 'Phòng Công Nghệ';
});

function actionClass(kind) {
    if (kind === 'primary') {
        return 'border-brand/40 bg-brand/20 text-white hover:bg-brand/35';
    }
    if (kind === 'mailto') {
        return 'border-cyan-500/30 bg-cyan-500/10 text-cyan-50/95 hover:bg-cyan-500/18';
    }
    return 'border-white/12 bg-white/[0.05] text-white/85 hover:bg-white/[0.1]';
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
    if (panelOpen.value) {
        startIdleCycle();
    }
});

watch(panelOpen, (on) => {
    if (on) {
        startIdleCycle();
    } else {
        clearInterval(idleTimer);
        actionsOpen.value = false;
    }
});

onMounted(() => {
    if (typeof window !== 'undefined' && window.matchMedia('(max-width: 639px)').matches) {
        panelOpen.value = false;
    }
    startIdleCycle();
});

onBeforeUnmount(() => clearInterval(idleTimer));

function togglePanel() {
    panelOpen.value = !panelOpen.value;
}

const {
    hidden,
    isDragging,
    dockStyle,
    onRailPointerDown,
    hideAssistant,
    showAssistant,
} = useCongngheAssistantDock(asideRef);

function onHideClick() {
    panelOpen.value = false;
    hideAssistant();
}
</script>

<template>
  <button
    v-if="hidden"
    type="button"
    class="fixed z-[45] flex items-center gap-2 rounded-full border border-white/15 bg-[#0c0e18]/92 px-3.5 py-2.5 text-xs font-semibold text-white/90 shadow-[0_12px_40px_-8px_rgba(154,0,54,0.55)] backdrop-blur-md transition hover:border-brand/45 hover:text-white"
    style="right: max(0.75rem, env(safe-area-inset-right)); bottom: max(0.75rem, env(safe-area-inset-bottom));"
    aria-label="Hiện trợ lý VAS"
    @click="showAssistant"
  >
    <span class="relative flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-brand/20 ring-1 ring-brand/40">
      <img
        :src="congngheBrand.mascotWave"
        alt=""
        class="cn-assistant-tab-mascot h-7 w-auto object-contain"
        decoding="async"
      >
    </span>
    Trợ lý VAS
  </button>

  <aside
    v-else
    ref="asideRef"
    class="cn-assistant fixed z-[45] flex max-h-[calc(100vh-4rem)] flex-row items-stretch"
    :class="{ 'cn-assistant--dragging': isDragging }"
    :style="dockStyle"
    aria-live="polite"
    aria-label="Trợ lý ảo Phòng Công Nghệ"
  >
    <div class="flex min-w-0 w-[min(calc(100vw-2.5rem),19rem)] flex-col items-end sm:w-[20.5rem]">
      <Transition
        enter-active-class="transition duration-250 ease-out"
        enter-from-class="translate-y-2 opacity-0 scale-[0.98]"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="translate-y-2 opacity-0 scale-[0.98]"
      >
        <div
          v-if="panelOpen && !isDragging"
          class="cn-assistant-panel mb-2 w-full overflow-hidden rounded-2xl border border-white/12 bg-[#0a0b14]/95 shadow-[0_20px_50px_-16px_rgba(154,0,54,0.5)] backdrop-blur-xl"
        >
          <div class="flex items-center gap-2 border-b border-white/8 bg-gradient-to-r from-brand/15 via-transparent to-cyan-500/10 px-3 py-2.5">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand/25 ring-1 ring-brand/35">
              <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                class="text-brand-200"
                aria-hidden="true"
              ><path d="M12 3v3M12 18v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M3 12h3M18 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12" /></svg>
            </span>
            <div class="min-w-0 flex-1">
              <p class="truncate font-mono text-[9px] font-semibold uppercase tracking-[0.16em] text-cyan-200/55">
                Trợ lý VAS
              </p>
              <p class="truncate text-xs font-semibold text-white/90">
                {{ sectionLabel }}
              </p>
            </div>
            <button
              type="button"
              class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-white/45 transition hover:bg-white/10 hover:text-white"
              aria-label="Thu gọn hộp thoại"
              @click="panelOpen = false"
            >
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              ><path d="m6 9 6 6 6-6" /></svg>
            </button>
          </div>

          <div class="px-3.5 py-3">
            <p
              :key="`${activeId}-${tipIndex}`"
              class="text-[13px] leading-relaxed text-white/88"
              :class="prefersReducedMotionNow() ? '' : 'animate-cn-rise'"
            >
              {{ currentLine }}
            </p>
            <div
              v-if="context.lines.length > 1"
              class="mt-2.5 flex gap-1"
              aria-hidden="true"
            >
              <span
                v-for="(_, i) in context.lines"
                :key="i"
                class="h-1 rounded-full transition-all duration-300"
                :class="i === tipIndex % context.lines.length ? 'w-4 bg-brand' : 'w-1 bg-white/25'"
              />
            </div>

            <div class="mt-3 border-t border-white/8 pt-2">
              <button
                type="button"
                class="flex w-full items-center justify-between gap-2 rounded-xl px-1 py-2 text-left transition hover:bg-white/[0.04]"
                :aria-expanded="actionsOpen"
                @click="actionsOpen = !actionsOpen"
              >
                <span class="font-mono text-[9px] font-semibold uppercase tracking-[0.14em] text-white/45">
                  Bạn muốn
                </span>
                <svg
                  width="16"
                  height="16"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                  class="shrink-0 text-white/40 transition-transform duration-200"
                  :class="actionsOpen ? 'rotate-180' : ''"
                  aria-hidden="true"
                ><path d="m6 9 6 6 6-6" /></svg>
              </button>
              <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0 -translate-y-1"
              >
                <ul
                  v-if="actionsOpen"
                  class="mt-1 space-y-1.5 pb-1"
                >
                  <li
                    v-for="action in quickActions"
                    :key="action.key"
                  >
                    <a
                      :href="action.href"
                      class="block rounded-xl border px-2.5 py-2 text-[12px] font-medium leading-snug transition"
                      :class="actionClass(action.kind)"
                    >
                      {{ action.label }}
                    </a>
                  </li>
                </ul>
              </Transition>
            </div>
          </div>
        </div>
      </Transition>

      <div class="cn-assistant-dock relative flex w-full justify-end">
        <button
          type="button"
          class="cn-assistant-mascot group relative cursor-pointer select-none border-0 bg-transparent p-0"
          :aria-expanded="panelOpen"
          aria-label="Trợ lý VAS — bấm để mở hoặc thu gọn hộp thoại"
          @click="togglePanel"
        >
          <span
            class="cn-assistant-mascot-glow pointer-events-none absolute -inset-4 rounded-full bg-[radial-gradient(circle,rgba(154,0,54,0.35),transparent_70%)] opacity-80 transition group-hover:opacity-100"
            aria-hidden="true"
          />
          <span
            class="cn-assistant-mascot-ring pointer-events-none absolute -inset-2 rounded-[1.25rem] border border-white/10 bg-[#05060c]/40 backdrop-blur-sm"
            aria-hidden="true"
          />
          <CongngheMascotAnimated
            :src="mascotSrc"
            alt=""
            variant="assistant"
            class="relative z-10"
          />
          <span
            v-if="!panelOpen"
            class="absolute -bottom-0.5 -left-0.5 h-2.5 w-2.5 rounded-full bg-brand shadow-[0_0_10px_rgba(255,77,141,0.9)] ring-2 ring-[#05060c]"
            aria-hidden="true"
          />
        </button>
        <button
          type="button"
          class="absolute -right-1 -top-1 z-20 grid h-7 w-7 place-items-center rounded-full border border-white/15 bg-[#0c0e18]/95 text-white/75 shadow-lg backdrop-blur-sm transition hover:border-rose-400/40 hover:bg-[#151828] hover:text-white"
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
        class="mt-1.5 w-full text-right text-[9px] leading-snug text-white/35"
      >
        Thanh bên phải: kéo lên/xuống · Bấm linh vật để {{ panelOpen ? 'thu gọn' : 'mở' }}
      </p>
    </div>

    <button
      type="button"
      class="cn-assistant-rail shrink-0 touch-none"
      aria-label="Kéo lên hoặc xuống để đặt vị trí trợ lý (cạnh thanh cuộn)"
      @pointerdown="onRailPointerDown"
    >
      <span
        class="cn-assistant-rail-track"
        aria-hidden="true"
      />
    </button>
  </aside>
</template>

<style scoped>
.cn-assistant {
    transition: top 0.15s ease-out;
}

.cn-assistant--dragging {
    transition: none;
}

.cn-assistant-rail {
    width: 12px;
    margin-left: 2px;
    cursor: ns-resize;
    border: none;
    padding: 0;
    background: transparent;
    align-self: stretch;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cn-assistant-rail-track {
    width: 4px;
    height: min(100%, 5.5rem);
    min-height: 3rem;
    border-radius: 999px;
    background: linear-gradient(
        180deg,
        rgba(255, 255, 255, 0.08),
        rgba(255, 77, 141, 0.45) 45%,
        rgba(255, 255, 255, 0.08)
    );
    box-shadow: 0 0 12px rgba(255, 77, 141, 0.25);
    transition: width 0.2s ease, box-shadow 0.2s ease;
}

.cn-assistant-rail:hover .cn-assistant-rail-track,
.cn-assistant-rail:focus-visible .cn-assistant-rail-track {
    width: 5px;
    box-shadow: 0 0 16px rgba(255, 77, 141, 0.45);
}

.cn-assistant--dragging .cn-assistant-rail-track {
    width: 6px;
    background: linear-gradient(
        180deg,
        rgba(56, 189, 248, 0.35),
        rgba(255, 77, 141, 0.65) 50%,
        rgba(56, 189, 248, 0.35)
    );
}

.cn-assistant--dragging .cn-assistant-mascot-glow {
    opacity: 1;
}

.cn-assistant-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    padding: 1px;
    background: linear-gradient(135deg, rgba(255, 77, 141, 0.4), rgba(56, 189, 248, 0.22), transparent 60%);
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

.cn-assistant-mascot {
    cursor: pointer;
}

.cn-assistant-tab-mascot {
    mix-blend-mode: screen;
}

@media (prefers-reduced-motion: reduce) {
    .cn-assistant,
    .cn-assistant-panel,
    .cn-assistant-panel p {
        transition: none !important;
        animation: none !important;
    }
}
</style>
