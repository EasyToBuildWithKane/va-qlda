<script setup>
import { computed, ref } from 'vue';
import SectionHeading from './SectionHeading.vue';
import DataStreamTicker from './DataStreamTicker.vue';
import AnalyzingBadge from './AnalyzingBadge.vue';
import RevealOnScroll from './RevealOnScroll.vue';
import CongngheProjectShowcase from './CongngheProjectShowcase.vue';
import { useInView, useScrollScene } from './motion.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
    products: { type: Array, default: () => [] },
});

const { target, shown: sectionVisible } = useInView({ threshold: 0.1 });
const sceneProgress = useScrollScene(target);

const heading = computed(() => props.content?.heading ?? {});

const slideCount = computed(() => props.products.length);

// Hiển thị từng dự án thành một dải full-width; xếp dọc. Quá nhiều thì gập bớt.
const INITIAL_VISIBLE = 3;
const expanded = ref(false);
const visibleProducts = computed(() =>
    expanded.value ? props.products : props.products.slice(0, INITIAL_VISIBLE),
);
const hiddenCount = computed(() => Math.max(0, props.products.length - INITIAL_VISIBLE));

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

      <!-- Mỗi sản phẩm là một dải full-width (gallery + thông tin nổi bật) -->
      <div
        v-if="slideCount"
        class="mt-10 flex flex-col gap-8 sm:gap-10"
      >
        <RevealOnScroll
          v-for="(product, i) in visibleProducts"
          :key="product.id"
          variant="up"
          :threshold="0.08"
          class="block"
        >
          <CongngheProjectShowcase
            :project="product"
            :index="i"
          />
        </RevealOnScroll>
      </div>

      <div
        v-if="hiddenCount && !expanded"
        class="mt-9 flex justify-center"
      >
        <button
          type="button"
          class="group inline-flex items-center gap-2 rounded-full border border-emerald-400/25 bg-emerald-400/[0.06] px-6 py-3 text-sm font-semibold text-emerald-100 transition hover:border-emerald-400/50 hover:bg-emerald-400/[0.12] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-400"
          @click="expanded = true"
        >
          Xem tất cả {{ slideCount }} sản phẩm
          <svg
            class="transition-transform duration-300 group-hover:translate-y-0.5"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          ><path d="M6 9l6 6 6-6" /></svg>
        </button>
      </div>

      <p
        v-if="!slideCount"
        class="mt-12 rounded-2xl border border-dashed border-emerald-400/20 bg-emerald-400/[0.04] px-6 py-12 text-center text-sm text-white/45"
      >
        Chưa có dự án nào hoàn thành. Dữ liệu sẽ hiển thị khi có sản phẩm được nghiệm thu.
      </p>
    </div>
  </section>
</template>
