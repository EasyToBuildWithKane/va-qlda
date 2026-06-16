<script setup>
import { computed } from 'vue';
import SectionHeading from './SectionHeading.vue';
import DataStreamTicker from './DataStreamTicker.vue';
import AnalyzingBadge from './AnalyzingBadge.vue';
import RevealOnScroll from './RevealOnScroll.vue';
import CongngheProjectShowcase from './CongngheProjectShowcase.vue';
import CongngheProjectSlider from './CongngheProjectSlider.vue';
import { useInView, useScrollScene } from './motion.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
});

const { target, shown: sectionVisible } = useInView({ threshold: 0.1 });
const sceneProgress = useScrollScene(target);

const heading = computed(() => props.content?.heading ?? {});

const slideCount = computed(() => props.products.length);

// 1 sản phẩm chính (mới nhất) hiển thị full-width nổi bật; phần còn lại vào swiper.
const featured = computed(() => props.products[0] ?? null);
const rest = computed(() => props.products.slice(1));

// Blob nền drift theo scroll + ticker mã sản phẩm (dữ liệu thật).
const blobA = computed(() => ({ transform: `translateY(${((sceneProgress.value - 0.5) * 60).toFixed(1)}px)` }));
const blobB = computed(() => ({ transform: `translateY(${((0.5 - sceneProgress.value) * 60).toFixed(1)}px)` }));
const streamItems = computed(() => props.products.map(
    (p) => `${p.code || p.name} · ${p.progress ?? 0}%`,
));
</script>

<template>
  <section
    id="san-pham"
    ref="target"
    class="relative py-20"
    tabindex="-1"
  >
    <div
      aria-hidden="true"
      class="pointer-events-none absolute inset-0 -z-0"
    >
      <div
        class="absolute left-[4%] top-16 h-56 w-56 rounded-full bg-emerald-500/12 blur-[100px] animate-cn-float"
        :style="blobA"
      />
      <div
        class="absolute right-[6%] bottom-6 h-64 w-64 rounded-full bg-cyan-500/10 blur-[100px] animate-cn-float-x"
        :style="blobB"
      />
    </div>

    <div class="relative mx-auto min-w-0 max-w-7xl px-5 sm:px-8">
      <div
        class="flex min-w-0 flex-wrap items-end justify-between gap-4 transition-all duration-700"
        :class="sectionVisible ? 'translate-y-0 opacity-100' : 'translate-y-5 opacity-0'"
      >
        <SectionHeading
          class="min-w-0 max-w-2xl flex-1"
          :eyebrow="heading.eyebrow"
          :title="heading.title"
          :subtitle="heading.subtitle"
        />
        <div
          v-if="slideCount"
          class="flex shrink-0 flex-col items-end gap-2"
        >
          <span class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/[0.07] px-3 py-1 font-mono text-[11px] uppercase tracking-wider text-emerald-200/90">
            <span class="grid place-items-center font-display text-sm font-bold tabular-nums leading-none text-emerald-100">{{ slideCount }}</span>
            sản phẩm đã nghiệm thu
          </span>
          <AnalyzingBadge
            label="AI giám sát vận hành"
            tone="emerald"
          />
        </div>
      </div>

      <DataStreamTicker
        v-if="slideCount"
        class="mt-5"
        :items="streamItems"
        variant="marquee"
        :speed="34"
      />

      <!-- 1 sản phẩm chính full-width nổi bật + swiper các sản phẩm còn lại -->
      <div
        v-if="featured"
        class="mt-10"
      >
        <RevealOnScroll
          variant="up"
          :threshold="0.08"
          class="block"
        >
          <CongngheProjectShowcase
            :project="featured"
            :index="0"
          />
        </RevealOnScroll>

        <div
          v-if="rest.length"
          class="mt-14"
        >
          <div class="mb-5 flex items-center gap-3">
            <span class="font-mono text-[11px] uppercase tracking-[0.18em] text-white/45">Các sản phẩm khác</span>
            <span class="h-px flex-1 bg-gradient-to-r from-emerald-400/30 to-transparent" />
            <span class="font-mono text-[11px] tabular-nums text-white/35">{{ rest.length }}</span>
          </div>
          <RevealOnScroll
            variant="up"
            class="block"
          >
            <CongngheProjectSlider
              :projects="rest"
              accent="emerald"
            />
          </RevealOnScroll>
        </div>
      </div>

      <p
        v-else
        class="mt-12 rounded-2xl border border-dashed border-emerald-400/20 bg-emerald-400/[0.04] px-6 py-12 text-center text-sm text-white/45"
      >
        Chưa có dự án nào hoàn thành. Dữ liệu sẽ hiển thị khi có sản phẩm được nghiệm thu.
      </p>
    </div>
  </section>
</template>
