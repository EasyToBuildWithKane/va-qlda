<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import MagneticButton from './MagneticButton.vue';
import CountStat from './CountStat.vue';
import { hasFinePointer, prefersReducedMotionNow } from './motion.js';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandBackdrop from './CongngheBrandBackdrop.vue';
import CongngheMascotAnimated from './CongngheMascotAnimated.vue';

const props = defineProps({
    content: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({}) },
});

const px = ref(0);
const py = ref(0);
const ready = ref(false);

function onMove(e) {
    px.value = (e.clientX / window.innerWidth - 0.5);
    py.value = (e.clientY / window.innerHeight - 0.5);
}

function layer(depth) {
    return { transform: `translate3d(${(px.value * depth).toFixed(1)}px, ${(py.value * depth).toFixed(1)}px, 0)` };
}

onMounted(() => {
    requestAnimationFrame(() => (ready.value = true));
    if (hasFinePointer() && !prefersReducedMotionNow()) {
        window.addEventListener('mousemove', onMove, { passive: true });
    }
});
onBeforeUnmount(() => window.removeEventListener('mousemove', onMove));

const titlePrefix = computed(() => props.content?.title_prefix ?? '');
const titleHighlight = computed(() => props.content?.title_highlight ?? '');
const titleSuffix = computed(() => props.content?.title_suffix ?? '');
const description = computed(() => props.content?.description ?? '');
const ctaPrimary = computed(() => props.content?.cta_primary ?? {});
const ctaSecondary = computed(() => props.content?.cta_secondary ?? {});
const highlights = computed(() => props.content?.highlights ?? []);
</script>

<template>
  <section
    id="top"
    class="relative flex min-h-[100dvh] flex-col justify-center overflow-hidden px-4 pb-20 pt-[5.5rem] sm:px-6 sm:pb-16 sm:pt-32 md:px-8 lg:min-h-screen lg:pt-36"
  >
    <CongngheBrandBackdrop
      variant="dragon"
      align="right"
      opacity-class="opacity-[0.04]"
    />
    <div class="pointer-events-none absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-brand/12 via-cyan-500/5 to-transparent" />

    <div
      class="relative z-10 mx-auto grid w-full max-w-7xl gap-10 transition-all duration-700 lg:grid-cols-2 lg:items-center lg:gap-12 xl:gap-16"
      :class="ready ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'"
    >
      <!-- Cột trái: copy + CTA + KPI -->
      <div class="flex flex-col items-center text-center lg:items-start lg:text-left">
        <h1 class="font-display text-[2.35rem] font-extrabold leading-[1.06] tracking-tight text-white sm:text-5xl lg:text-[3rem] xl:text-[3.25rem]">
          {{ titlePrefix }}
          <span
            v-if="titleHighlight"
            class="bg-gradient-to-r from-cyan-200 via-brand-200 to-violet-300 bg-clip-text text-transparent"
          >{{ titleHighlight }}</span>
          {{ titleSuffix }}
        </h1>

        <p class="mt-5 max-w-xl text-base leading-relaxed text-white/60 sm:text-lg">
          {{ description }}
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3 lg:justify-start">
          <MagneticButton
            v-if="ctaPrimary.label"
            :href="ctaPrimary.anchor || '#'"
            variant="primary"
          >
            {{ ctaPrimary.label }}
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </MagneticButton>
          <MagneticButton
            v-if="ctaSecondary.label"
            :href="ctaSecondary.anchor || '#'"
            variant="ghost"
          >
            {{ ctaSecondary.label }}
          </MagneticButton>
        </div>

        <dl class="mt-10 grid w-full max-w-lg grid-cols-3 gap-4 sm:gap-6">
          <div
            v-for="h in highlights"
            :key="h.key"
          >
            <dt class="font-display text-2xl font-bold text-white sm:text-3xl">
              <CountStat
                :value="Number(metrics[h.key] ?? 0)"
                :active="ready"
              /><span class="bg-gradient-to-r from-brand to-cyan-300/80 bg-clip-text text-transparent">{{ h.suffix }}</span>
            </dt>
            <dd class="mt-1 font-mono text-[10px] uppercase tracking-wider text-white/45">
              {{ h.label }}
            </dd>
          </div>
        </dl>
      </div>

      <!-- Cột phải: mascot (desktop — mobile dùng trợ lý ảo) -->
      <div
        class="relative mx-auto hidden w-full max-w-md items-center justify-center lg:flex lg:max-w-none lg:justify-end"
        :style="layer(-6)"
      >
        <CongngheMascotAnimated
          :src="congngheBrand.mascotWave"
          alt="Linh vật VAS — Phòng Công Nghệ"
          variant="hero"
          :parallax-style="layer(10)"
        />
      </div>
    </div>

    <a
      href="#gioi-thieu"
      class="absolute bottom-6 left-1/2 z-10 -translate-x-1/2 text-white/35 transition hover:text-white"
      aria-label="Cuộn xuống"
    >
      <svg
        width="26"
        height="26"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        class="animate-bounce"
      ><path d="M12 5v14M6 13l6 6 6-6" /></svg>
    </a>
  </section>
</template>
