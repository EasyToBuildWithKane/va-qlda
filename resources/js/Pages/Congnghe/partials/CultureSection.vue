<script setup>
import { computed, inject } from 'vue';
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import RippleSurface from './RippleSurface.vue';
import ScanLineOverlay from './ScanLineOverlay.vue';
import CongngheBrandBackdrop from './CongngheBrandBackdrop.vue';
import SectionParticleNetwork from './SectionParticleNetwork.vue';
import { useInView } from './motion.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const { target, shown } = useInView();

const icons = inject('congngheIcons', {});
const heading = computed(() => props.content?.heading ?? {});
const values = computed(() => props.content?.values ?? []);

function iconPath(key) {
    return icons[key] ?? '';
}
</script>

<template>
  <section
    id="van-hoa"
    ref="target"
    class="relative overflow-hidden scroll-mt-24 py-16 sm:scroll-mt-28 sm:py-20 md:py-24"
  >
    <SectionParticleNetwork
      tone="brand"
      :seed="23"
    />
    <CongngheBrandBackdrop
      variant="badge"
      align="left"
      opacity-class="opacity-[0.04]"
    />
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-brand/30 via-white/10 to-transparent"
      aria-hidden="true"
    />

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 md:px-8">
      <SectionHeading
        :eyebrow="heading.eyebrow"
        :title="heading.title"
        :subtitle="heading.subtitle"
      />

      <div class="mt-10 grid gap-4 sm:mt-12 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3 lg:gap-6">
        <GlassCard
          v-for="(v, i) in values"
          :key="v.title"
          tilt
          :padded="false"
          class="p-6 transition-all duration-700 sm:p-7"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
          :style="{ transitionDelay: `${120 + i * 80}ms` }"
        >
          <template #overlay>
            <ScanLineOverlay
              trigger="hover"
              tone="brand"
            />
            <RippleSurface />
          </template>
          <div class="flex items-center justify-between">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-gradient-to-br from-brand/80 to-[#ff4d8d]/80 text-white shadow-lg shadow-brand/20 transition-transform duration-300 group-hover/glass:scale-110">
              <svg
                width="22"
                height="22"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
              ><path :d="iconPath(v.icon)" /></svg>
            </span>
            <span class="bg-gradient-to-r from-white/45 to-white/10 bg-[length:200%_auto] bg-clip-text font-mono text-[11px] uppercase tracking-wider text-transparent group-hover/glass:animate-cn-text-shimmer">0{{ i + 1 }}</span>
          </div>
          <h3 class="mt-4 font-display text-base font-bold text-white">
            {{ v.title }}
          </h3>
          <p class="mt-2 text-sm leading-relaxed text-white/55">
            {{ v.body }}
          </p>
        </GlassCard>
      </div>
    </div>
  </section>
</template>
