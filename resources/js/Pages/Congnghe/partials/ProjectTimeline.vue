<script setup>
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import { tone } from './tones.js';
import { useInView } from './motion.js';

defineProps({
    phases: { type: Array, default: () => [] },
});

const { target, shown } = useInView({ threshold: 0.1 });
</script>

<template>
  <section
    id="du-an"
    ref="target"
    class="relative py-28"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        eyebrow="Dự án đang triển khai"
        title="Vòng đời sản phẩm số"
        subtitle="Từ nghiên cứu phát triển, triển khai nghiệm thu đến vận hành cải tiến — mỗi dự án đi qua một hành trình rõ ràng."
      />

      <div class="relative mt-14">
        <!-- flow line across phases (desktop) -->
        <div class="pointer-events-none absolute inset-x-[12%] top-[30px] hidden h-px bg-gradient-to-r from-transparent via-brand/40 to-transparent lg:block" />

        <div class="grid gap-5 lg:grid-cols-3">
          <GlassCard
            v-for="(phase, i) in phases"
            :key="phase.value"
            :padded="false"
            class="p-6 transition-all duration-700"
            :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
            :style="{ transitionDelay: `${i * 130}ms` }"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span
                  class="relative grid h-10 w-10 place-items-center rounded-xl font-display text-sm font-bold ring-1 ring-inset"
                  :class="tone(phase.color).soft"
                >
                  {{ i + 1 }}
                  <span
                    class="absolute inset-0 rounded-xl"
                    :class="tone(phase.color).dot"
                    style="opacity: 0.18; filter: blur(8px);"
                  />
                </span>
                <div>
                  <p class="font-mono text-[10px] uppercase tracking-wider text-white/40">
                    Phase {{ i + 1 }}
                  </p>
                  <h3 class="font-display text-base font-bold text-white">
                    {{ phase.label }}
                  </h3>
                </div>
              </div>
              <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 font-mono text-[11px] font-semibold text-white/60">
                {{ phase.total }}
              </span>
            </div>

            <div class="mt-5 space-y-3">
              <div
                v-for="project in phase.items"
                :key="project.id"
                class="rounded-xl border border-white/10 bg-white/[0.025] p-4 transition-colors hover:border-white/20 hover:bg-white/[0.05]"
              >
                <div class="flex items-center gap-3">
                  <span
                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: project.color || '#9A0036', boxShadow: `0 0 8px ${project.color || '#9A0036'}` }"
                  />
                  <p class="min-w-0 flex-1 truncate text-[13.5px] font-semibold text-white">
                    {{ project.name }}
                  </p>
                  <span class="shrink-0 font-mono text-[12px] font-semibold text-white/55">{{ project.progress }}%</span>
                </div>
                <div class="mt-2.5 h-1 overflow-hidden rounded-full bg-white/10">
                  <div
                    class="h-full rounded-full bg-[linear-gradient(110deg,#9A0036,#ff4d8d,#9A0036)] bg-[length:200%_100%] animate-cn-shimmer"
                    :style="{ width: `${project.progress}%` }"
                  />
                </div>
              </div>

              <p
                v-if="!phase.items.length"
                class="rounded-xl border border-dashed border-white/10 px-4 py-6 text-center text-[12.5px] text-white/40"
              >
                Chưa có dự án ở giai đoạn này.
              </p>
            </div>
          </GlassCard>
        </div>
      </div>
    </div>
  </section>
</template>
