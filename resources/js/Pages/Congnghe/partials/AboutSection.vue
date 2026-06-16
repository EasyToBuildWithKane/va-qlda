<script setup>
import { computed, inject } from 'vue';
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import { useInView } from './motion.js';
import CongngheBrandBackdrop from './CongngheBrandBackdrop.vue';
import SectionParticleNetwork from './SectionParticleNetwork.vue';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const { target, shown } = useInView();

const icons = inject('congngheIcons', {});
const heading = computed(() => props.content?.heading ?? {});
const pillars = computed(() => props.content?.pillars ?? []);

function iconPath(key) {
    return icons[key] ?? '';
}
</script>

<template>
  <section
    id="gioi-thieu"
    ref="target"
    class="relative overflow-hidden scroll-mt-24 py-16 sm:scroll-mt-28 sm:py-20 md:py-24"
  >
    <SectionParticleNetwork
      tone="brand"
      :seed="3"
    />
    <CongngheBrandBackdrop
      variant="badge"
      align="left"
      opacity-class="opacity-[0.05]"
    />
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-brand/40 to-transparent"
      aria-hidden="true"
    />

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 md:px-8">
      <div
        :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
        style="transition: opacity 0.7s ease, transform 0.7s ease"
      >
        <SectionHeading
          :eyebrow="heading.eyebrow"
          :title="heading.title"
          :subtitle="heading.subtitle"
        />
      </div>

      <div class="mt-10 grid gap-5 sm:mt-12 sm:gap-6 lg:grid-cols-3 lg:gap-6 xl:gap-8">
        <GlassCard
          v-for="(p, i) in pillars"
          :key="p.title"
          tilt
          :padded="false"
          class="min-w-0 p-6 transition-all duration-700 sm:p-7 lg:p-8"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
          :style="{ transitionDelay: `${120 + i * 100}ms` }"
        >
          <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-300">0{{ i + 1 }}</span>
          <div class="mt-3 grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-brand to-[#ff4d8d] p-3 text-white shadow-lg shadow-brand/30">
            <svg
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
              stroke-linejoin="round"
            ><path :d="iconPath(p.icon)" /></svg>
          </div>
          <p
            v-if="p.tag"
            class="mt-5 font-mono text-[11px] font-semibold uppercase tracking-[0.18em] text-white/45"
          >
            {{ p.tag }}
          </p>
          <h3 class="mt-1.5 font-display text-lg font-bold text-white sm:text-xl">
            {{ p.title }}
          </h3>
          <p class="mt-3 text-sm leading-relaxed text-white/55">
            {{ p.body }}
          </p>
        </GlassCard>
      </div>
    </div>
  </section>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
    section [style*='transition'] {
        transition: none !important;
    }
}
</style>
