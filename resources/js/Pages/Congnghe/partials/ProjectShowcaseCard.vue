<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { tone } from './tones.js';
import { useTilt } from './motion.js';
import { openCongngheProject } from './useCongngheProjectModal.js';
import { richContentPlainText } from '@/shared/utils/richContent';

/**
 * Thẻ sản phẩm/dự án dùng chung cho "Hệ sinh thái sản phẩm" và "Vòng đời sản
 * phẩm số" — đảm bảo hai section đồng nhất ngôn ngữ thị giác. Glass tối + viền
 * conic xoay theo màu thương hiệu của dự án, lưới mesh động, góc HUD, tilt 3D +
 * glare theo con trỏ, ảnh bìa có scan-line, thanh tiến độ shimmer, chân thẻ là
 * người phụ trách. Click ⇒ mở modal chi tiết dự án.
 */
const props = defineProps({
    project: { type: Object, required: true },
    // Biến thể lớn nằm ngang (ảnh trái — nội dung phải) cho card nổi bật.
    featured: { type: Boolean, default: false },
    // Ẩn thanh tiến độ (vd. khi tất cả đều 100% và muốn gọn hơn).
    showProgress: { type: Boolean, default: true },
});

const { el, style } = useTilt({ max: props.featured ? 5 : 7, scale: props.featured ? 1.008 : 1.02 });
// Bọc ref qua computed để template không auto-unwrap (giống GlassCard).
const cardRef = computed(() => el);

const hue = computed(() => props.project?.color || '#9A0036');
const statusTone = computed(() => tone(props.project?.statusColor));
const cover = computed(() => props.project?.images?.[0]?.url ?? null);
const extraImages = computed(() => {
    const n = props.project?.images?.length ?? 0;
    return n > 1 ? n - 1 : 0;
});
const initials = computed(() => (props.project?.code || props.project?.name || '?').slice(0, 2));
const summary = computed(
    () => richContentPlainText(props.project?.description) || 'Nền tảng đã nghiệm thu và đưa vào vận hành.',
);
const progress = computed(() => Math.max(0, Math.min(100, Number(props.project?.progress ?? 0))));

function open() {
    openCongngheProject(props.project);
}
</script>

<template>
  <article
    class="cn-card group/card relative h-full"
    :style="{ '--card-hue': hue }"
  >
    <!-- Halo nền toả sáng khi hover -->
    <span
      class="cn-card__halo pointer-events-none absolute -inset-2 rounded-[1.6rem] opacity-0 transition-opacity duration-500 group-hover/card:opacity-100"
      aria-hidden="true"
    />

    <button
      :ref="cardRef"
      type="button"
      :style="style"
      class="cn-card__btn relative flex h-full w-full flex-col overflow-hidden rounded-[1.35rem] border border-white/10 bg-[#0a0c16]/92 text-left shadow-[0_18px_50px_-26px_rgba(0,0,0,0.9)] ring-1 ring-white/[0.04] backdrop-blur-md transition-[transform,border-color,box-shadow] duration-300 will-change-transform focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--card-hue)]"
      :class="featured ? 'lg:flex-row' : ''"
      @click="open"
    >
      <!-- Viền conic xoay theo màu dự án -->
      <span
        class="cn-card__border pointer-events-none absolute inset-0 rounded-[1.35rem]"
        aria-hidden="true"
      />
      <!-- Lưới mesh động -->
      <span
        class="cn-card__mesh pointer-events-none absolute inset-0 rounded-[1.35rem] opacity-30"
        aria-hidden="true"
      />
      <!-- Glare theo con trỏ (tilt) -->
      <span
        class="cn-card__glare pointer-events-none absolute inset-0 rounded-[1.35rem] opacity-0 transition-opacity duration-300 group-hover/card:opacity-100"
        aria-hidden="true"
      />
      <!-- Góc HUD -->
      <span
        class="cn-card__corner cn-card__corner--tl pointer-events-none absolute left-2.5 top-2.5"
        aria-hidden="true"
      />
      <span
        class="cn-card__corner cn-card__corner--tr pointer-events-none absolute right-2.5 top-2.5"
        aria-hidden="true"
      />
      <span
        class="cn-card__corner cn-card__corner--bl pointer-events-none absolute bottom-2.5 left-2.5"
        aria-hidden="true"
      />
      <span
        class="cn-card__corner cn-card__corner--br pointer-events-none absolute bottom-2.5 right-2.5"
        aria-hidden="true"
      />

      <!-- Ảnh bìa -->
      <div
        class="relative shrink-0 overflow-hidden bg-white/[0.04]"
        :class="featured
          ? 'aspect-[16/10] lg:aspect-auto lg:w-[52%] lg:self-stretch'
          : 'aspect-[16/9]'"
      >
        <img
          v-if="cover"
          :src="cover"
          alt=""
          class="cn-card__cover h-full w-full object-cover"
          loading="lazy"
          decoding="async"
        >
        <div
          v-else
          class="flex h-full w-full items-center justify-center"
          :style="{ background: `linear-gradient(135deg, ${hue}55, #0a0c16 72%)` }"
        >
          <span class="font-display text-5xl font-bold uppercase text-white/20">{{ initials }}</span>
        </div>

        <!-- Scan-line khi hover -->
        <span
          class="cn-card__scan pointer-events-none absolute inset-x-0 top-0 h-2/5 opacity-0"
          aria-hidden="true"
        />
        <!-- Phủ tối để chữ nổi (chỉ ở biến thể dọc; biến thể ngang giữ ảnh rõ) -->
        <div
          v-if="!featured"
          class="pointer-events-none absolute inset-0 bg-gradient-to-t from-[#0a0c16] via-[#0a0c16]/15 to-transparent"
        />

        <!-- Chip trạng thái -->
        <span
          class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full border bg-black/45 px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-wider backdrop-blur-md"
          :class="statusTone.soft"
        >
          <span class="relative flex h-1.5 w-1.5">
            <span
              class="absolute inline-flex h-full w-full animate-cn-ping-ring rounded-full"
              :class="statusTone.dot"
            />
            <span
              class="relative inline-flex h-1.5 w-1.5 rounded-full"
              :class="statusTone.dot"
            />
          </span>
          {{ project.status }}
        </span>

        <!-- Đếm ảnh -->
        <span
          v-if="extraImages"
          class="absolute bottom-2.5 right-2.5 inline-flex items-center gap-1 rounded-md bg-black/55 px-2 py-0.5 font-mono text-[9px] text-white/75 backdrop-blur-sm"
        >
          <svg
            width="11"
            height="11"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          ><rect
            x="3"
            y="3"
            width="18"
            height="18"
            rx="2"
          /><path d="m21 15-5-5L5 21" /></svg>
          +{{ extraImages }}
        </span>

        <!-- Nhãn loại vòng đời (góc dưới-trái) -->
        <span
          v-if="project.type_label"
          class="absolute bottom-2.5 left-3 inline-flex items-center rounded-full border border-white/10 bg-black/45 px-2 py-0.5 font-mono text-[9px] uppercase tracking-wider text-white/70 backdrop-blur-md"
        >
          {{ project.type_label }}
        </span>
      </div>

      <!-- Nội dung -->
      <div
        class="relative flex min-w-0 flex-1 flex-col"
        :class="featured ? 'p-5 sm:p-7' : 'p-4 sm:p-5'"
      >
        <p
          v-if="project.code"
          class="font-mono text-[10px] uppercase tracking-[0.2em] [color:color-mix(in_srgb,var(--card-hue)_75%,white)]"
        >
          {{ project.code }}
        </p>
        <h3
          class="mt-1.5 font-display font-bold leading-snug text-white"
          :class="featured ? 'text-xl sm:text-2xl' : 'line-clamp-2 text-[17px] sm:text-lg'"
        >
          {{ project.name }}
        </h3>
        <p
          class="mt-2 leading-relaxed text-white/55"
          :class="featured ? 'line-clamp-4 text-sm' : 'line-clamp-3 text-xs sm:text-[13px]'"
        >
          {{ summary }}
        </p>

        <!-- Tiến độ -->
        <div
          v-if="showProgress"
          class="mt-4"
          :class="featured ? 'sm:mt-5' : ''"
        >
          <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-wider text-white/45">
            <span>Tiến độ</span>
            <span class="text-[13px] font-semibold tabular-nums text-white/85">{{ progress }}%</span>
          </div>
          <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/[0.08]">
            <div
              class="cn-card__bar h-full rounded-full"
              :style="{ width: `${progress}%` }"
            />
          </div>
        </div>

        <!-- Chân thẻ: phụ trách + CTA -->
        <div class="mt-auto flex items-center justify-between gap-2 border-t border-white/[0.08] pt-4">
          <div
            v-if="project.manager"
            class="flex min-w-0 items-center gap-2.5"
          >
            <Avatar
              :name="project.manager.name"
              :src="project.manager.avatar"
              :size="featured ? 36 : 30"
            />
            <span class="min-w-0">
              <span class="block truncate text-xs font-medium text-white/75">{{ project.manager.name }}</span>
              <span
                v-if="featured && project.manager.role_title"
                class="block truncate text-[11px] text-white/40"
              >{{ project.manager.role_title }}</span>
            </span>
          </div>
          <span
            v-else
            class="text-xs text-white/35"
          >Chưa gán phụ trách</span>

          <span class="cn-card__cta inline-flex shrink-0 items-center gap-0.5 text-xs font-semibold text-[color:color-mix(in_srgb,var(--card-hue)_80%,white)]">
            Chi tiết
            <svg
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </span>
        </div>
      </div>
    </button>
  </article>
</template>

<style scoped>
.cn-card__btn {
    transform-style: preserve-3d;
}

.cn-card__halo {
    background: radial-gradient(
        ellipse 75% 60% at 50% 35%,
        color-mix(in srgb, var(--card-hue, #9a0036) 42%, transparent),
        transparent 70%
    );
    filter: blur(16px);
}

.group\/card:hover .cn-card__btn {
    border-color: color-mix(in srgb, var(--card-hue, #9a0036) 45%, transparent);
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--card-hue, #9a0036) 18%, transparent),
        0 28px 64px -26px color-mix(in srgb, var(--card-hue, #9a0036) 45%, transparent),
        0 18px 50px -26px rgba(0, 0, 0, 0.9);
}

/* Viền conic xoay — nền tảng dùng @property --cn-card-angle */
.cn-card__border {
    padding: 1px;
    background: conic-gradient(
        from var(--cn-card-angle, 0deg),
        color-mix(in srgb, var(--card-hue, #9a0036) 80%, transparent),
        rgba(34, 211, 238, 0.55),
        rgba(167, 139, 250, 0.5),
        color-mix(in srgb, var(--card-hue, #9a0036) 80%, transparent)
    );
    -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    opacity: 0.3;
    animation: cn-card-border-spin 9s linear infinite;
}

.group\/card:hover .cn-card__border {
    opacity: 0.85;
}

.cn-card__mesh {
    background-image:
        linear-gradient(color-mix(in srgb, var(--card-hue, #9a0036) 28%, transparent) 1px, transparent 1px),
        linear-gradient(90deg, color-mix(in srgb, var(--card-hue, #9a0036) 28%, transparent) 1px, transparent 1px);
    background-size: 26px 26px;
    mask-image: radial-gradient(ellipse 90% 70% at 50% 0%, #000 10%, transparent 75%);
    -webkit-mask-image: radial-gradient(ellipse 90% 70% at 50% 0%, #000 10%, transparent 75%);
    animation: cn-card-grid-pan 11s linear infinite;
}

.cn-card__glare {
    background: radial-gradient(
        260px circle at var(--cn-mx, 50%) var(--cn-my, 50%),
        rgba(255, 255, 255, 0.14),
        transparent 60%
    );
}

.cn-card__corner {
    width: 0.85rem;
    height: 0.85rem;
    border-color: color-mix(in srgb, var(--card-hue, #9a0036) 70%, white);
    opacity: 0;
    transition: opacity 0.35s, width 0.35s, height 0.35s;
}

.cn-card__corner--tl {
    border-top: 1.5px solid;
    border-left: 1.5px solid;
    border-top-left-radius: 0.5rem;
}
.cn-card__corner--tr {
    border-top: 1.5px solid;
    border-right: 1.5px solid;
    border-top-right-radius: 0.5rem;
}
.cn-card__corner--bl {
    border-bottom: 1.5px solid;
    border-left: 1.5px solid;
    border-bottom-left-radius: 0.5rem;
}
.cn-card__corner--br {
    border-bottom: 1.5px solid;
    border-right: 1.5px solid;
    border-bottom-right-radius: 0.5rem;
}

.group\/card:hover .cn-card__corner {
    opacity: 0.9;
    width: 1.2rem;
    height: 1.2rem;
}

.cn-card__cover {
    transition: transform 0.7s cubic-bezier(0.22, 1, 0.36, 1), filter 0.5s;
}

.group\/card:hover .cn-card__cover {
    transform: scale(1.07);
    filter: saturate(1.1) brightness(1.04);
}

.cn-card__scan {
    background: linear-gradient(
        180deg,
        transparent 0%,
        color-mix(in srgb, var(--card-hue, #9a0036) 30%, transparent) 45%,
        rgba(255, 255, 255, 0.12) 50%,
        rgba(34, 211, 238, 0.2) 55%,
        transparent 100%
    );
}

.group\/card:hover .cn-card__scan {
    opacity: 1;
    animation: cn-card-scan 2.4s ease-in-out infinite;
}

.cn-card__bar {
    background: linear-gradient(
        110deg,
        color-mix(in srgb, var(--card-hue, #9a0036) 90%, black),
        color-mix(in srgb, var(--card-hue, #9a0036) 60%, white),
        color-mix(in srgb, var(--card-hue, #9a0036) 90%, black)
    );
    background-size: 200% 100%;
    animation: cn-shimmer 6s linear infinite;
}

.cn-card__cta {
    transition: gap 0.3s, transform 0.3s;
}

.group\/card:hover .cn-card__cta {
    gap: 0.35rem;
    transform: translateX(2px);
}

@property --cn-card-angle {
    syntax: '<angle>';
    initial-value: 0deg;
    inherits: false;
}

@keyframes cn-card-border-spin {
    to {
        --cn-card-angle: 360deg;
    }
}

@keyframes cn-card-grid-pan {
    0% {
        background-position: 0 0;
    }
    100% {
        background-position: 26px 26px;
    }
}

@keyframes cn-card-scan {
    0% {
        transform: translateY(-120%);
    }
    100% {
        transform: translateY(260%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .cn-card__border,
    .cn-card__mesh,
    .cn-card__scan,
    .cn-card__bar,
    .cn-card__cover {
        animation: none;
    }

    .cn-card__btn,
    .group\/card:hover .cn-card__cover {
        transform: none !important;
    }
}
</style>
