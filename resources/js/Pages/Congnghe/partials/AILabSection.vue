<script setup>
import { computed, inject } from 'vue';
import SectionHeading from './SectionHeading.vue';
import HologramCard from './HologramCard.vue';
import NeuralBackdrop from './NeuralBackdrop.vue';
import AnalyzingBadge from './AnalyzingBadge.vue';
import TypewriterText from './TypewriterText.vue';
import { useInView } from './motion.js';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const { target, shown } = useInView({ threshold: 0.2 });

const icons = inject('congngheIcons', {});
const heading = computed(() => props.content?.heading ?? {});
const initiatives = computed(() => props.content?.initiatives ?? []);

function iconPath(key) {
    return icons[key] ?? '';
}
</script>

<template>
  <section
    id="ai-lab"
    ref="target"
    class="relative overflow-hidden py-20"
  >
    <!-- Mạng nơ-ron nền (giả lập "AI đang xử lý") -->
    <NeuralBackdrop
      class="opacity-50"
      :node-count="22"
      tone="cyan"
    />

    <div class="relative z-10 mx-auto max-w-7xl px-5 sm:px-8">
      <div class="grid gap-12 lg:grid-cols-[0.95fr_1.05fr] lg:items-center">
        <div>
          <SectionHeading
            :eyebrow="heading.eyebrow"
            :title="heading.title"
          />
          <p class="mt-4 max-w-md text-base leading-relaxed text-white/55">
            <TypewriterText
              :text="heading.subtitle"
              :active="shown"
              :speed="14"
            />
          </p>
          <div class="mt-5">
            <AnalyzingBadge label="AI · innovation lab" />
          </div>
        </div>

        <div class="grid gap-4">
          <HologramCard
            v-for="(item, i) in initiatives"
            :key="item.title"
            tone="cyan"
            class="transition-all duration-700"
            :class="shown ? 'translate-x-0 opacity-100' : 'translate-x-6 opacity-0'"
            :style="{ transitionDelay: `${i * 120}ms` }"
          >
            <div class="flex items-start gap-4">
              <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/5 text-brand-300 ring-1 ring-white/10">
                <svg
                  width="22"
                  height="22"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                ><path :d="iconPath(item.icon)" /></svg>
              </span>
              <div>
                <h3 class="font-display text-base font-bold text-white">
                  {{ item.title }}
                </h3>
                <p class="mt-1.5 text-sm leading-relaxed text-white/55">
                  <TypewriterText
                    :text="item.body"
                    :active="shown"
                    :speed="8"
                    :start-delay="400 + i * 220"
                    :caret="false"
                  />
                </p>
              </div>
            </div>
          </HologramCard>
        </div>
      </div>
    </div>
  </section>
</template>
