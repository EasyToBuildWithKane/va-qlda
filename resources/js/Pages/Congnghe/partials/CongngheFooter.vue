<script setup>
import { computed } from 'vue';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandImage from './CongngheBrandImage.vue';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const colHeadingClass =
    'min-h-[2.75rem] font-mono text-[11px] font-semibold uppercase leading-snug tracking-[0.18em] text-cyan-200/70';

const brandTitle = computed(() => props.content?.brand_title ?? 'Vietnam America Schools');
const brandTagline = computed(() => props.content?.brand_tagline ?? '');
const brandDesc = computed(() => props.content?.brand_desc ?? '');
const exploreLinks = computed(() => props.content?.explore_links ?? []);
const contactLinks = computed(() => props.content?.contact_links ?? []);
const copyright = computed(() => props.content?.copyright ?? '');
const portalLabel = computed(() => props.content?.portal_label ?? '');

const year = new Date().getFullYear();
</script>

<template>
  <footer class="relative overflow-hidden border-t border-brand/40 bg-gradient-to-br from-[#120818] via-brand/25 to-[#05060c]">
    <div
      class="pointer-events-none absolute inset-0 opacity-40"
      aria-hidden="true"
      style="background-image: radial-gradient(circle at 20% 0%, rgba(154,0,54,0.35), transparent 45%), radial-gradient(circle at 80% 100%, rgba(56,189,248,0.12), transparent 40%);"
    />
    <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-cyan-400/30 via-brand to-violet-500/30" />

    <div class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 md:px-8 md:py-12">
      <div
        class="cn-footer-grid grid grid-cols-1 gap-10 sm:grid-cols-2 sm:gap-8 lg:grid-cols-4 lg:gap-6 xl:gap-8"
      >
        <div class="cn-footer-col flex min-w-0 flex-col text-center sm:text-left">
          <p :class="colHeadingClass">
            Phòng Công Nghệ
          </p>
          <div class="mt-4 flex flex-col items-center gap-3 sm:items-start">
            <CongngheBrandImage
              :src="congngheBrand.wordmarkStacked"
              :alt="brandTitle"
              class="h-auto w-52 shrink-0 sm:w-60"
              loading="lazy"
            />
            <div class="min-w-0 w-full">
              <p
                v-if="brandTagline"
                class="text-sm text-white/65"
              >
                {{ brandTagline }}
              </p>
            </div>
          </div>
          <p
            v-if="brandDesc"
            class="mt-4 text-sm leading-relaxed text-white/55"
          >
            {{ brandDesc }}
          </p>
        </div>

        <div class="cn-footer-col flex min-w-0 flex-col text-center sm:text-left">
          <p :class="colHeadingClass">
            Khám phá
          </p>
          <ul class="mt-4 space-y-2.5">
            <li
              v-for="link in exploreLinks"
              :key="link.label"
            >
              <a
                :href="link.href"
                class="text-sm text-white/65 transition hover:text-white"
              >{{ link.label }}</a>
            </li>
          </ul>
        </div>

        <div class="cn-footer-col flex min-w-0 flex-col text-center sm:text-left">
          <p :class="colHeadingClass">
            Liên lạc
          </p>
          <ul class="mt-4 space-y-2.5">
            <li
              v-for="link in contactLinks"
              :key="link.label"
            >
              <a
                :href="link.href"
                class="break-words text-sm text-white/65 transition hover:text-white"
              >{{ link.label }}</a>
            </li>
          </ul>
        </div>

        <div class="cn-footer-col flex min-w-0 flex-col text-center">
          <p :class="colHeadingClass">
            Đồng hành cùng VAS
          </p>
          <div class="relative mt-4 flex flex-1 flex-col items-center justify-center gap-3">
            <CongngheBrandImage
              :src="congngheBrand.mascotVaJacket"
              alt="Linh vật VAS"
              class="h-36 w-auto max-w-full sm:h-40 lg:h-44"
              loading="lazy"
            />
            <p class="mx-auto max-w-xs text-center text-sm leading-relaxed text-white/55">
              Cùng kiến tạo nền tảng số — từ ý tưởng đến sản phẩm vận hành thật.
            </p>
          </div>
        </div>
      </div>

      <div class="mt-10 flex flex-col items-center justify-between gap-4 border-t border-white/15 pt-6 sm:flex-row sm:gap-6">
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
        <p class="shrink-0 text-center text-[12.5px] text-white/40 sm:text-right">
          {{ portalLabel }} · {{ year }}
        </p>
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
</style>
