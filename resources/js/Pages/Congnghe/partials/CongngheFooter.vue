<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandImage from './CongngheBrandImage.vue';
import { useInView } from './motion.js';
import { useCongngheMotion } from './useCongngheMotion.js';

const page = usePage();

const { target, shown } = useInView({ threshold: 0.12 });
const { reduced: motionReduced, toggle: toggleMotion } = useCongngheMotion();

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const onLanding = computed(() => {
    const path = page.url.split('?')[0].replace(/\/$/, '') || '/';
    return path === '/congnghe';
});

function resolveHref(href) {
    const value = String(href ?? '').trim();
    if (value === '') {
        return value;
    }
    if (value.startsWith('#')) {
        return onLanding.value ? value : `/congnghe${value}`;
    }
    return value;
}

const brandTitle = computed(() => props.content?.brand_title ?? 'Vietnam America Schools');
const brandTagline = computed(() => props.content?.brand_tagline ?? '');
const brandDesc = computed(() => props.content?.brand_desc ?? '');
const exploreLinks = computed(() => (props.content?.explore_links ?? []).map((link) => ({
    ...link,
    href: resolveHref(link.href),
})));
const contactLinks = computed(() => (props.content?.contact_links ?? []).map((link) => ({
    ...link,
    href: resolveHref(link.href),
})));
const copyright = computed(() => props.content?.copyright ?? '');
const portalLabel = computed(() => props.content?.portal_label ?? '');

const year = new Date().getFullYear();
</script>

<template>
  <footer class="relative overflow-hidden border-t border-brand/40 bg-[#06070f]">
    <!-- Quầng sáng nền -->
    <div
      class="pointer-events-none absolute inset-0 opacity-50"
      aria-hidden="true"
      style="background-image: radial-gradient(circle at 18% -10%, rgba(154,0,54,0.4), transparent 46%), radial-gradient(circle at 86% 120%, rgba(56,189,248,0.14), transparent 42%);"
    />
    <!-- Lưới ma trận -->
    <div
      class="pointer-events-none absolute inset-0 opacity-[0.16]"
      aria-hidden="true"
      style="
        background-image:
          linear-gradient(rgba(148,163,184,0.6) 1px, transparent 1px),
          linear-gradient(90deg, rgba(148,163,184,0.6) 1px, transparent 1px);
        background-size: 46px 46px;
        mask-image: radial-gradient(ellipse 80% 90% at 50% 0%, #000 5%, transparent 70%);
        -webkit-mask-image: radial-gradient(ellipse 80% 90% at 50% 0%, #000 5%, transparent 70%);
      "
    />
    <!-- Hairline gradient cuộn ở mép trên -->
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px animate-cn-text-shimmer bg-gradient-to-r from-cyan-400/30 via-brand to-violet-500/30 bg-[length:200%_100%]" />

    <!-- Ngoặc HUD góc -->
    <span
      class="pointer-events-none absolute left-3 top-3 h-5 w-5 rounded-tl-md border-l border-t border-cyan-400/30"
      aria-hidden="true"
    />
    <span
      class="pointer-events-none absolute right-3 top-3 h-5 w-5 rounded-tr-md border-r border-t border-brand/40"
      aria-hidden="true"
    />

    <div class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 md:px-8 md:py-12">
      <!-- Thanh trạng thái kiểu console -->
      <div class="mb-9 flex flex-col gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3 backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between">
        <div class="flex min-w-0 items-center gap-2 overflow-hidden font-mono text-[12px]">
          <span class="text-emerald-300/80">▸</span>
          <span class="shrink-0 text-white/35">~/phong-cong-nghe</span>
          <span class="text-cyan-200/70">$</span>
          <span class="truncate text-white/70">trang_trang_thai --live</span>
          <span class="ml-0.5 inline-block h-3.5 w-[7px] animate-cn-caret-blink bg-cyan-300/80" />
        </div>
        <div class="flex shrink-0 items-center gap-4 font-mono text-[11px]">
          <span class="inline-flex items-center gap-1.5 uppercase tracking-wider text-emerald-300/90">
            <span class="relative flex h-2 w-2">
              <span class="absolute inline-flex h-full w-full animate-cn-ping-ring rounded-full bg-emerald-400/70" />
              <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400" />
            </span>
            Đang vận hành
          </span>
          <span
            v-if="portalLabel"
            class="text-white/35"
          >{{ portalLabel }} · {{ year }}</span>
        </div>
      </div>

      <div
        ref="target"
        class="cn-footer-grid grid grid-cols-1 gap-10 sm:grid-cols-2 sm:gap-8 lg:grid-cols-4 lg:gap-6 xl:gap-8"
        :class="{ 'cn-revealed': shown }"
      >
        <!-- Cột thương hiệu -->
        <div class="cn-footer-col flex min-w-0 flex-col text-center sm:text-left">
          <p class="flex items-center justify-center gap-2 font-mono text-[11px] font-semibold uppercase leading-snug tracking-[0.18em] text-cyan-200/70 sm:justify-start">
            <span class="h-3 w-[3px] rounded-full bg-gradient-to-b from-brand to-cyan-400/60" />
            <span class="text-white/30">//</span> Phòng Công Nghệ
          </p>
          <div class="mt-4 flex flex-col items-center gap-3 sm:items-start">
            <CongngheBrandImage
              :src="congngheBrand.wordmarkStacked"
              :alt="brandTitle"
              class="h-auto w-52 shrink-0 sm:w-60"
              loading="lazy"
            />
            <p
              v-if="brandTagline"
              class="min-w-0 text-sm text-white/65"
            >
              {{ brandTagline }}
            </p>
          </div>
          <p
            v-if="brandDesc"
            class="mt-4 text-sm leading-relaxed text-white/55"
          >
            {{ brandDesc }}
          </p>
          <p class="mt-4 inline-flex items-center gap-1.5 self-center font-mono text-[10px] uppercase tracking-wider text-white/30 sm:self-start">
            <span class="text-brand/70">⌁</span> node://vaschools.edu.vn
          </p>
        </div>

        <!-- Cột Khám phá -->
        <div class="cn-footer-col flex min-w-0 flex-col text-center sm:text-left">
          <p class="flex items-center justify-center gap-2 font-mono text-[11px] font-semibold uppercase leading-snug tracking-[0.18em] text-cyan-200/70 sm:justify-start">
            <span class="h-3 w-[3px] rounded-full bg-gradient-to-b from-brand to-cyan-400/60" />
            <span class="text-white/30">//</span> Khám phá
          </p>
          <ul class="mt-4 space-y-2.5">
            <li
              v-for="link in exploreLinks"
              :key="link.label"
              class="flex justify-center sm:justify-start"
            >
              <a
                :href="link.href"
                class="group inline-flex items-center gap-2 text-sm text-white/60 transition hover:text-white"
              >
                <span class="font-mono text-brand/70 transition-transform duration-300 group-hover:translate-x-0.5">›</span>
                <span class="relative">
                  {{ link.label }}
                  <span class="absolute -bottom-0.5 left-0 h-px w-0 bg-gradient-to-r from-brand to-cyan-400/60 transition-all duration-300 group-hover:w-full" />
                </span>
              </a>
            </li>
          </ul>
        </div>

        <!-- Cột Liên lạc -->
        <div class="cn-footer-col flex min-w-0 flex-col text-center sm:text-left">
          <p class="flex items-center justify-center gap-2 font-mono text-[11px] font-semibold uppercase leading-snug tracking-[0.18em] text-cyan-200/70 sm:justify-start">
            <span class="h-3 w-[3px] rounded-full bg-gradient-to-b from-brand to-cyan-400/60" />
            <span class="text-white/30">//</span> Liên lạc
          </p>
          <ul class="mt-4 space-y-2.5">
            <li
              v-for="link in contactLinks"
              :key="link.label"
              class="flex justify-center sm:justify-start"
            >
              <a
                :href="link.href"
                class="group inline-flex min-w-0 items-start gap-2 text-sm text-white/60 transition hover:text-white"
              >
                <span class="mt-0.5 font-mono text-brand/70 transition-transform duration-300 group-hover:translate-x-0.5">›</span>
                <span class="relative break-words">
                  {{ link.label }}
                  <span class="absolute -bottom-0.5 left-0 h-px w-0 bg-gradient-to-r from-brand to-cyan-400/60 transition-all duration-300 group-hover:w-full" />
                </span>
              </a>
            </li>
          </ul>
        </div>

        <!-- Cột Linh vật -->
        <div class="cn-footer-col flex min-w-0 flex-col text-center">
          <p class="flex items-center justify-center gap-2 font-mono text-[11px] font-semibold uppercase leading-snug tracking-[0.18em] text-cyan-200/70">
            <span class="h-3 w-[3px] rounded-full bg-gradient-to-b from-brand to-cyan-400/60" />
            <span class="text-white/30">//</span> Đồng hành cùng VAS
          </p>
          <div class="relative mt-4 flex flex-1 flex-col items-center justify-center gap-3">
            <div class="relative">
              <span class="pointer-events-none absolute inset-0 -z-0 rounded-full bg-brand/20 blur-2xl" />
              <CongngheBrandImage
                :src="congngheBrand.mascotVaJacket"
                alt="Linh vật VAS"
                class="relative h-36 w-auto max-w-full sm:h-40 lg:h-44"
                loading="lazy"
              />
            </div>
            <p class="mx-auto max-w-xs text-center text-sm leading-relaxed text-white/55">
              Cùng kiến tạo nền tảng số — từ ý tưởng đến sản phẩm vận hành thật.
            </p>
          </div>
        </div>
      </div>

      <!-- Thanh dưới cùng -->
      <div class="mt-10 flex flex-col items-center justify-between gap-4 border-t border-white/12 pt-6 sm:flex-row sm:gap-6">
        <div class="flex flex-wrap items-center justify-center gap-3 sm:justify-start">
          <CongngheBrandImage
            :src="congngheBrand.badgeCircle"
            alt=""
            class="h-8 w-8 shrink-0 opacity-90"
            loading="lazy"
          />
          <p class="max-w-xl text-center text-[12.5px] leading-relaxed text-white/50 sm:text-left">
            {{ copyright }}
          </p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center justify-center gap-3 sm:justify-end">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-full border border-white/12 bg-white/[0.03] px-3 py-1.5 font-mono text-[11px] text-white/55 transition hover:border-brand/40 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/50"
            :aria-pressed="motionReduced"
            :title="motionReduced ? 'Bật lại hiệu ứng động' : 'Giảm hiệu ứng động (tiết kiệm pin, mượt hơn trên máy yếu)'"
            @click="toggleMotion"
          >
            <span
              class="relative flex h-2 w-2"
              aria-hidden="true"
            >
              <span
                v-if="!motionReduced"
                class="absolute inline-flex h-full w-full animate-cn-ping-ring rounded-full bg-cyan-400/70"
              />
              <span
                class="relative inline-flex h-2 w-2 rounded-full"
                :class="motionReduced ? 'bg-white/30' : 'bg-cyan-400'"
              />
            </span>
            {{ motionReduced ? 'Hiệu ứng: Giảm' : 'Hiệu ứng: Đầy đủ' }}
          </button>
          <p class="text-center font-mono text-[11px] text-white/35 sm:text-right">
            <span class="text-white/25">[</span>
            {{ portalLabel }} · v{{ year }}
            <span class="text-white/25">]</span>
          </p>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
@media (min-width: 1024px) {
    .cn-footer-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .cn-footer-col {
        width: 100%;
    }
}

/* Reveal-on-scroll từng cột, lệch nhịp nhẹ */
.cn-footer-col {
    opacity: 0;
    transform: translateY(16px);
    transition: opacity 0.7s ease, transform 0.7s ease;
}

.cn-revealed .cn-footer-col {
    opacity: 1;
    transform: none;
}

.cn-revealed .cn-footer-col:nth-child(2) {
    transition-delay: 0.08s;
}

.cn-revealed .cn-footer-col:nth-child(3) {
    transition-delay: 0.16s;
}

.cn-revealed .cn-footer-col:nth-child(4) {
    transition-delay: 0.24s;
}

@media (prefers-reduced-motion: reduce) {
    .cn-footer-col {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
