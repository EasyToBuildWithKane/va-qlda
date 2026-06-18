<script setup>
import { computed } from 'vue';
import SectionHeading from './SectionHeading.vue';
import DataStreamTicker from './DataStreamTicker.vue';
import AnalyzingBadge from './AnalyzingBadge.vue';
import RevealOnScroll from './RevealOnScroll.vue';
import CongngheProjectCarousel from './CongngheProjectCarousel.vue';
import SectionParticleNetwork from './SectionParticleNetwork.vue';
import { useInView, useScrollScene } from './motion.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
});

const { target, shown: sectionVisible } = useInView({ threshold: 0.1 });
const sceneProgress = useScrollScene(target);

const heading = computed(() => props.content?.heading ?? {});

const slideCount = computed(() => props.products.length);

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
    class="relative overflow-hidden scroll-mt-24 py-16 sm:scroll-mt-28 sm:py-20 md:py-24"
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

    <SectionParticleNetwork
      tone="emerald"
      :seed="11"
    />

    <div class="relative z-10 mx-auto min-w-0 max-w-7xl px-5 sm:px-8">
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

      <!-- Tất cả sản phẩm trong một carousel full-width, mỗi lần 1 slide -->
      <RevealOnScroll
        v-if="slideCount"
        variant="up"
        :threshold="0.08"
        class="mt-10 block"
      >
        <CongngheProjectCarousel
          :projects="products"
          accent="emerald"
        />
      </RevealOnScroll>

      <p
        v-else
        class="mt-12 rounded-2xl border border-dashed border-emerald-400/20 bg-emerald-400/[0.04] px-6 py-12 text-center text-sm text-white/45"
      >
        Chưa có dự án nào hoàn thành. Dữ liệu sẽ hiển thị khi có sản phẩm được nghiệm thu.
      </p>
    </div>
  </section>
</template>
