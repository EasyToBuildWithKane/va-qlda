<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';
import CongngheProjectGallery from './CongngheProjectGallery.vue';
import { tone } from './tones.js';
import { openCongngheProject } from './useCongngheProjectModal.js';
import { richContentPlainText } from '@/shared/utils/richContent';

/**
 * Dải dự án full-width "chuẩn công nghệ": mỗi dự án chiếm trọn chiều rộng, một
 * bên là gallery ảnh tham chiếu (xem được, phóng to), một bên là khối thông tin
 * nổi bật (mã, loại, trạng thái, tên lớn, mô tả, tiến độ, phụ trách + CTA). Ảnh
 * đổi bên trái/phải xen kẽ theo thứ tự. Bấm tên hoặc «Xem chi tiết» ⇒ mở modal.
 */
const props = defineProps({
    project: { type: Object, required: true },
    // Thứ tự trong danh sách — quyết định đảo bên ảnh + số thứ tự hiển thị.
    index: { type: Number, default: 0 },
});

const hue = computed(() => props.project?.color || '#9A0036');
const reverse = computed(() => props.index % 2 === 1);
const statusTone = computed(() => tone(props.project?.statusColor));
const progress = computed(() => Math.max(0, Math.min(100, Number(props.project?.progress ?? 0))));
const imageCount = computed(() => (props.project?.images ?? []).filter((i) => i?.url).length);
const summary = computed(
    () => richContentPlainText(props.project?.description) || 'Nền tảng đã nghiệm thu và đưa vào vận hành.',
);
const ordinal = computed(() => String(props.index + 1).padStart(2, '0'));

function open() {
    openCongngheProject(props.project);
}
</script>

<template>
  <article
    class="cn-show group relative"
    :style="{ '--hue': hue }"
  >
    <!-- Halo nền toả sáng khi hover -->
    <span
      class="cn-show__halo pointer-events-none absolute -inset-px rounded-[1.9rem] opacity-60 transition-opacity duration-500 group-hover:opacity-100"
      aria-hidden="true"
    />

    <div
      class="cn-show__panel relative overflow-hidden rounded-[1.75rem] border border-white/10 bg-[#0a0c16]/85 shadow-[0_30px_90px_-40px_rgba(0,0,0,0.95)] ring-1 ring-white/[0.04] backdrop-blur-md transition-[border-color,box-shadow] duration-500"
    >
      <!-- Viền conic xoay theo màu dự án -->
      <span
        class="cn-show__border pointer-events-none absolute inset-0 rounded-[1.75rem]"
        aria-hidden="true"
      />
      <!-- Lưới mesh -->
      <span
        class="cn-show__mesh pointer-events-none absolute inset-0 rounded-[1.75rem] opacity-[0.18]"
        aria-hidden="true"
      />
      <!-- Góc HUD -->
      <span
        class="cn-show__corner cn-show__corner--tl pointer-events-none absolute left-3 top-3"
        aria-hidden="true"
      />
      <span
        class="cn-show__corner cn-show__corner--br pointer-events-none absolute bottom-3 right-3"
        aria-hidden="true"
      />

      <div
        class="relative grid items-center gap-6 p-5 sm:gap-8 sm:p-7 lg:grid-cols-2 lg:gap-10 lg:p-9"
      >
        <!-- ── Cột gallery (đổi bên xen kẽ trên desktop) ── -->
        <div
          class="relative min-w-0"
          :class="reverse ? 'lg:order-2' : 'lg:order-1'"
        >
          <!-- Số thứ tự lớn mờ phía sau gallery -->
          <span
            class="pointer-events-none absolute -top-7 left-0 select-none font-display text-7xl font-black leading-none text-white/[0.04] sm:text-8xl"
            aria-hidden="true"
          >{{ ordinal }}</span>
          <CongngheProjectGallery
            :key="project.id"
            :images="project.images"
            density="slide"
          />
        </div>

        <!-- ── Cột thông tin ── -->
        <div
          class="relative flex min-w-0 flex-col"
          :class="reverse ? 'lg:order-1' : 'lg:order-2'"
        >
          <!-- Eyebrow: mã · loại · trạng thái -->
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
              :class="statusTone.soft"
            >
              <span
                class="relative flex h-1.5 w-1.5"
              >
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
            <span
              v-if="project.code"
              class="font-mono text-[11px] uppercase tracking-[0.2em] [color:color-mix(in_srgb,var(--hue)_75%,white)]"
            >
              {{ project.code }}
            </span>
            <span
              v-if="project.type_label"
              class="inline-flex items-center rounded-full border border-white/12 bg-white/5 px-2.5 py-0.5 font-mono text-[10px] uppercase tracking-wider text-white/60"
            >
              {{ project.type_label }}
            </span>
          </div>

          <!-- Tên dự án (bấm để mở chi tiết) -->
          <h3 class="mt-3.5">
            <button
              type="button"
              class="cn-show__title text-left font-display text-2xl font-extrabold leading-tight text-white transition-colors hover:[color:color-mix(in_srgb,var(--hue)_55%,white)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[color:var(--hue)] sm:text-[1.85rem] lg:text-[2.1rem]"
              @click="open"
            >
              {{ project.name }}
            </button>
          </h3>

          <!-- Mô tả ngắn -->
          <p class="mt-3 line-clamp-3 max-w-prose leading-relaxed text-white/55 sm:text-[15px]">
            {{ summary }}
          </p>

          <!-- Tiến độ -->
          <div class="mt-5">
            <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-wider text-white/45">
              <span>Tiến độ nghiệm thu</span>
              <span class="text-[15px] font-bold tabular-nums text-white/90">{{ progress }}%</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/[0.08]">
              <div
                class="cn-show__bar h-full rounded-full"
                :style="{ width: `${progress}%` }"
              />
            </div>
          </div>

          <!-- Chân: phụ trách + CTA -->
          <div class="mt-7 flex flex-wrap items-center justify-between gap-4 border-t border-white/[0.08] pt-5">
            <div
              v-if="project.manager"
              class="flex min-w-0 items-center gap-3"
            >
              <Avatar
                :name="project.manager.name"
                :src="project.manager.avatar"
                :size="40"
              />
              <span class="min-w-0">
                <span class="block truncate text-sm font-semibold text-white/85">{{ project.manager.name }}</span>
                <span class="block truncate text-[12px] text-white/45">{{ project.manager.role_title || 'Phụ trách chính' }}</span>
              </span>
            </div>
            <span
              v-else
              class="text-sm text-white/35"
            >Chưa gán phụ trách</span>

            <button
              type="button"
              class="cn-show__cta group/cta inline-flex shrink-0 items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-semibold text-white transition-all duration-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[color:var(--hue)]"
              @click="open"
            >
              <span class="inline-flex items-center gap-1.5">
                <svg
                  width="15"
                  height="15"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                ><circle
                  cx="11"
                  cy="11"
                  r="7"
                /><path d="m21 21-4.3-4.3" /></svg>
                Xem chi tiết
              </span>
              <svg
                class="transition-transform duration-300 group-hover/cta:translate-x-0.5"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
              ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </button>
          </div>

          <!-- Đếm ảnh (gợi ý có gallery) -->
          <p
            v-if="imageCount"
            class="mt-3 inline-flex items-center gap-1.5 font-mono text-[10px] uppercase tracking-wider text-white/30"
          >
            <svg
              width="12"
              height="12"
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
            /><circle
              cx="8.5"
              cy="8.5"
              r="1.5"
            /><path d="m21 15-5-5L5 21" /></svg>
            {{ imageCount }} hình ảnh tham chiếu
          </p>
        </div>
      </div>
    </div>
  </article>
</template>

<style scoped>
.cn-show__halo {
    background: radial-gradient(
        ellipse 60% 70% at 50% 50%,
        color-mix(in srgb, var(--hue, #9a0036) 30%, transparent),
        transparent 72%
    );
    filter: blur(22px);
}

.group:hover .cn-show__panel {
    border-color: color-mix(in srgb, var(--hue, #9a0036) 40%, transparent);
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--hue, #9a0036) 16%, transparent),
        0 36px 100px -44px color-mix(in srgb, var(--hue, #9a0036) 50%, transparent),
        0 30px 90px -40px rgba(0, 0, 0, 0.95);
}

/* Viền conic xoay — nền tảng dùng @property --cn-show-angle */
.cn-show__border {
    padding: 1px;
    background: conic-gradient(
        from var(--cn-show-angle, 0deg),
        color-mix(in srgb, var(--hue, #9a0036) 80%, transparent),
        rgba(34, 211, 238, 0.5),
        rgba(167, 139, 250, 0.45),
        color-mix(in srgb, var(--hue, #9a0036) 80%, transparent)
    );
    -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    opacity: 0.22;
    animation: cn-show-spin 12s linear infinite;
}

.group:hover .cn-show__border {
    opacity: 0.7;
}

.cn-show__mesh {
    background-image:
        linear-gradient(color-mix(in srgb, var(--hue, #9a0036) 24%, transparent) 1px, transparent 1px),
        linear-gradient(90deg, color-mix(in srgb, var(--hue, #9a0036) 24%, transparent) 1px, transparent 1px);
    background-size: 30px 30px;
    mask-image: radial-gradient(ellipse 90% 80% at 50% 0%, #000 6%, transparent 72%);
    -webkit-mask-image: radial-gradient(ellipse 90% 80% at 50% 0%, #000 6%, transparent 72%);
}

.cn-show__corner {
    width: 1.1rem;
    height: 1.1rem;
    border-color: color-mix(in srgb, var(--hue, #9a0036) 70%, white);
    opacity: 0.35;
    transition: opacity 0.4s, width 0.4s, height 0.4s;
}

.cn-show__corner--tl {
    border-top: 1.5px solid;
    border-left: 1.5px solid;
    border-top-left-radius: 0.6rem;
}

.cn-show__corner--br {
    border-bottom: 1.5px solid;
    border-right: 1.5px solid;
    border-bottom-right-radius: 0.6rem;
}

.group:hover .cn-show__corner {
    opacity: 0.9;
    width: 1.5rem;
    height: 1.5rem;
}

.cn-show__bar {
    background: linear-gradient(
        110deg,
        color-mix(in srgb, var(--hue, #9a0036) 90%, black),
        color-mix(in srgb, var(--hue, #9a0036) 55%, white),
        color-mix(in srgb, var(--hue, #9a0036) 90%, black)
    );
    background-size: 200% 100%;
    animation: cn-shimmer 6s linear infinite;
}

.cn-show__cta {
    border-color: color-mix(in srgb, var(--hue, #9a0036) 45%, transparent);
    background: color-mix(in srgb, var(--hue, #9a0036) 14%, transparent);
}

.cn-show__cta:hover {
    border-color: color-mix(in srgb, var(--hue, #9a0036) 75%, transparent);
    background: color-mix(in srgb, var(--hue, #9a0036) 26%, transparent);
    box-shadow: 0 0 28px -8px color-mix(in srgb, var(--hue, #9a0036) 85%, transparent);
}

@property --cn-show-angle {
    syntax: '<angle>';
    initial-value: 0deg;
    inherits: false;
}

@keyframes cn-show-spin {
    to {
        --cn-show-angle: 360deg;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cn-show__border,
    .cn-show__bar {
        animation: none;
    }
}
</style>
