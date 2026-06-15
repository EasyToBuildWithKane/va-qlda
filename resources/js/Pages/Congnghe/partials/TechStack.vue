<script setup>
import { computed } from 'vue';
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import { useInView } from './motion.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const { target, shown } = useInView();

const heading = computed(() => props.content?.heading ?? {});
const groups = computed(() => props.content?.groups ?? []);
</script>

<template>
  <section
    id="cong-nghe"
    ref="target"
    class="relative py-20"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        :eyebrow="heading.eyebrow"
        :title="heading.title"
        :subtitle="heading.subtitle"
      />

      <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <GlassCard
          v-for="(group, i) in groups"
          :key="group.title"
          tilt
          :padded="false"
          class="p-6 transition-all duration-700"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
          :style="{ transitionDelay: `${i * 100}ms` }"
        >
          <div class="flex items-center justify-between">
            <h3 class="font-display text-sm font-bold uppercase tracking-wider text-white">
              {{ group.title }}
            </h3>
            <span
              v-if="group.tag"
              class="font-mono text-[10px] lowercase tracking-wide text-brand-300"
            >/{{ group.tag }}</span>
          </div>
          <ul class="mt-4 flex flex-wrap gap-2">
            <li
              v-for="item in (group.items ?? [])"
              :key="item"
              class="rounded-lg border border-white/10 bg-white/[0.04] px-2.5 py-1.5 font-mono text-[12px] text-white/75 transition-colors hover:border-brand/40 hover:text-white"
            >
              {{ item }}
            </li>
          </ul>
        </GlassCard>
      </div>
    </div>
  </section>
</template>
