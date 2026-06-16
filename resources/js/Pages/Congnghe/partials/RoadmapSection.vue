<script setup>
import { computed } from 'vue';
import SectionHeading from './SectionHeading.vue';
import GlassCard from './GlassCard.vue';
import { useInView, useScrollScene } from './motion.js';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandBackdrop from './CongngheBrandBackdrop.vue';
import CongngheBrandImage from './CongngheBrandImage.vue';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const { target, shown } = useInView({ threshold: 0.1 });
const drawProgress = useScrollScene(target, { start: 0.08, end: 0.65 });

const heading = computed(() => props.content?.heading ?? {});
const milestones = computed(() => props.content?.milestones ?? []);
const guideLabel = computed(() => props.content?.guide_label ?? '');
const companionNote = computed(() => props.content?.companion_note ?? '');
</script>

<template>
  <section
    id="lo-trinh"
    ref="target"
    class="relative overflow-hidden py-20"
  >
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand/[0.08] via-[#05060c] to-brand/[0.06]" />
    <CongngheBrandBackdrop
      variant="dragon"
      align="left"
      opacity-class="opacity-[0.04]"
    />

    <div class="relative mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        :eyebrow="heading.eyebrow"
        :title="heading.title"
        :subtitle="heading.subtitle"
      />

      <div class="mt-10 grid gap-10 lg:mt-12 lg:grid-cols-2 lg:items-center lg:gap-12 xl:gap-16">
        <!-- Cột trái: timeline -->
        <div class="min-w-0">
          <ol class="relative space-y-7 before:absolute before:left-[18px] before:top-3 before:h-[calc(100%-1.5rem)] before:w-px before:bg-white/10 sm:before:left-[22px]">
            <!-- Đường timeline "vẽ" dần theo scroll -->
            <span
              class="pointer-events-none absolute left-[18px] top-3 h-[calc(100%-1.5rem)] w-px origin-top bg-gradient-to-b from-brand via-[#ff4d8d] to-brand/40 sm:left-[22px]"
              :style="{ transform: `scaleY(${drawProgress})` }"
              aria-hidden="true"
            />
            <li
              v-for="(m, i) in milestones"
              :key="m.title"
              class="relative pl-14 transition-all duration-700 sm:pl-16"
              :class="shown ? 'translate-x-0 opacity-100' : (i % 2 ? 'translate-x-5 opacity-0' : '-translate-x-5 opacity-0')"
              :style="{ transitionDelay: `${i * 120}ms` }"
            >
              <span class="absolute left-0 top-1 grid h-9 w-9 place-items-center overflow-hidden rounded-full border border-brand/40 bg-white/[0.04] sm:h-11 sm:w-11">
                <CongngheBrandImage
                  v-if="i === 0"
                  :src="congngheBrand.badgeCircle"
                  alt=""
                  class="h-[85%] w-[85%]"
                />
                <span
                  v-else
                  class="font-mono text-[12px] font-bold text-brand-300"
                >{{ i + 1 }}</span>
                <span
                  v-if="m.live"
                  class="absolute inset-0 rounded-full ring-2 ring-brand/50 animate-cn-ping-ring"
                />
              </span>
              <GlassCard
                tilt
                :padded="false"
                class="p-6"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <p
                    v-if="m.period"
                    class="font-mono text-[12px] font-semibold uppercase tracking-wider text-brand-300"
                  >
                    {{ m.period }}
                  </p>
                  <span
                    v-if="m.state"
                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium"
                    :class="m.live ? 'border-emerald-400/20 bg-emerald-400/10 text-emerald-300' : 'border-white/10 bg-white/5 text-white/55'"
                  >
                    <span
                      v-if="m.live"
                      class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-cn-glow"
                    />
                    {{ m.state }}
                  </span>
                </div>
                <h3 class="mt-2 font-display text-lg font-bold text-white">
                  {{ m.title }}
                </h3>
                <p class="mt-1.5 text-sm leading-relaxed text-white/55">
                  {{ m.body }}
                </p>
              </GlassCard>
            </li>
          </ol>
        </div>

        <!-- Cột phải: mascot (ngang hàng, kích thước lớn) -->
        <aside
          class="relative flex min-h-[280px] flex-col items-center justify-center lg:min-h-[420px]"
          :class="shown ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'"
          style="transition: opacity 700ms, transform 700ms"
        >
          <div class="w-full max-w-md rounded-2xl border border-brand/20 bg-gradient-to-b from-brand/15 to-white/[0.03] p-5 backdrop-blur-sm lg:max-w-none lg:p-8">
            <p
              v-if="guideLabel"
              class="text-center font-mono text-[10px] font-semibold uppercase tracking-[0.2em] text-brand-300 sm:text-[11px]"
            >
              {{ guideLabel }}
            </p>
            <CongngheBrandImage
              :src="congngheBrand.mascotVaJacket"
              alt="Linh vật VAS đồng hành lộ trình công nghệ"
              class="mx-auto mt-4 w-full max-h-[min(52vh,420px)] drop-shadow-[0_20px_48px_rgba(154,0,54,0.35)] lg:mt-6 lg:max-h-[480px]"
              loading="lazy"
            />
          </div>
        </aside>
      </div>

      <div
        v-if="companionNote"
        class="mt-10 flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-5 sm:px-6 lg:hidden"
        :class="shown ? 'opacity-100' : 'opacity-0'"
        style="transition: opacity 700ms 400ms"
      >
        <CongngheBrandImage
          :src="congngheBrand.mascotVaJacket"
          alt=""
          class="h-24 w-auto shrink-0"
          loading="lazy"
        />
        <p class="text-sm leading-relaxed text-white/55">
          {{ companionNote }}
        </p>
      </div>
    </div>
  </section>
</template>
