<script setup>
import { computed } from 'vue';
import {
    HERO_TECH_ORBITS,
    ORBIT_TONE_CLASS,
    ORBIT_SWEEP_GRADIENT,
    ORBIT_COMET_COLOR,
    normalizeOrbitItem,
} from './congngheHeroTechOrbits.js';
import { prefersReducedMotionNow } from './motion.js';

const props = defineProps({
    active: { type: Boolean, default: true },
    /** Ghi đè vòng quỹ đạo (vd. từ CMS sau này) */
    orbits: { type: Array, default: null },
});

const reduced = prefersReducedMotionNow();

const rings = computed(() =>
    (props.orbits?.length ? props.orbits : HERO_TECH_ORBITS).map((ring) => ({
        ...ring,
        items: (ring.items ?? []).map(normalizeOrbitItem),
    })),
);

function itemAngle(index, count, offsetDeg) {
    if (count <= 0) return offsetDeg;
    return offsetDeg + (360 / count) * index;
}

function spinKeyframes(reverse) {
    return reverse ? 'normal' : 'reverse';
}

function ringSpinStyle(ring) {
    if (reduced) return {};
    return {
        animation: `cn-spin-slow ${ring.duration}s linear infinite`,
        animationDirection: ring.reverse ? 'reverse' : 'normal',
    };
}

function badgeSpinStyle(ring) {
    if (reduced) return {};
    return {
        animation: `cn-spin-slow ${ring.duration}s linear infinite`,
        animationDirection: spinKeyframes(ring.reverse),
    };
}

// Cung sáng quét quanh vòng — quay liên tục theo tốc độ riêng từng vòng.
function ringSweepStyle(ring) {
    if (reduced) return { display: 'none' };
    return {
        background: ORBIT_SWEEP_GRADIENT[ring.tone] || ORBIT_SWEEP_GRADIENT.cyan,
        animation: `cn-border-rotate ${Math.max(ring.duration * 0.5, 12)}s linear infinite`,
        animationDirection: ring.reverse ? 'reverse' : 'normal',
    };
}

function cometColor(ring) {
    return ORBIT_COMET_COLOR[ring.tone] || ORBIT_COMET_COLOR.cyan;
}
</script>

<template>
  <div
    class="pointer-events-none absolute inset-0 flex items-center justify-center"
    aria-hidden="true"
  >
    <div
      class="relative aspect-square w-full max-w-[min(100%,420px)] transition-all duration-1000 ease-out lg:max-w-[480px] xl:max-w-[520px]"
      :class="active ? 'scale-100 opacity-100' : 'scale-[0.92] opacity-0'"
    >
      <!-- Lõi phát sáng -->
      <div
        class="absolute inset-[38%] rounded-full bg-[radial-gradient(circle,rgba(154,0,54,0.35),rgba(34,211,238,0.08)_45%,transparent_72%)] blur-xl transition-opacity duration-700"
        :class="active ? 'opacity-100' : 'opacity-0'"
      />

      <div
        v-for="(ring, ringIndex) in rings"
        :key="`${ring.inset}-${ringIndex}`"
        class="absolute rounded-full transition-opacity duration-700"
        :class="active ? 'opacity-100' : 'opacity-0'"
        :style="{
          inset: ring.inset,
          transitionDelay: `${120 + ringIndex * 90}ms`,
        }"
      >
        <!-- Viền trang trí (tĩnh) -->
        <div
          class="absolute inset-0 rounded-full border border-dashed transition-colors duration-500"
          :class="[
            ring.tone === 'cyan' ? 'border-cyan-400/20' : '',
            ring.tone === 'violet' ? 'border-violet-400/18' : '',
            ring.tone === 'brand' ? 'border-brand/25' : '',
            ring.tone === 'emerald' ? 'border-emerald-400/18' : '',
          ]"
        />

        <!-- Cung sáng quét quanh vòng — quay liên tục (mọi vòng) -->
        <div
          class="absolute inset-[-1px] rounded-full opacity-60"
          :style="{
            ...ringSweepStyle(ring),
            padding: '1.5px',
            '-webkit-mask': 'linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0)',
            '-webkit-mask-composite': 'xor',
            'mask-composite': 'exclude',
          }"
        />

        <!-- Hành tinh nhỏ + comet trên quỹ đạo (quay cùng vòng) -->
        <div
          class="absolute inset-0"
          :style="ringSpinStyle(ring)"
        >
          <!-- Comet: đầu sáng chạy quanh vòng (trục xoay = tâm vòng) -->
          <div
            v-if="!reduced"
            class="absolute bottom-1/2 left-1/2 h-1/2 w-0 origin-bottom"
            :style="{ transform: `rotate(${ring.offsetDeg ?? 0}deg)` }"
          >
            <span
              class="absolute left-0 top-0 h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full"
              :style="{
                background: cometColor(ring),
                boxShadow: `0 0 12px 3px ${cometColor(ring)}`,
              }"
            />
          </div>

          <div
            v-for="(item, i) in ring.items"
            :key="`${item.label}-${i}`"
            class="absolute bottom-1/2 left-1/2 h-1/2 w-0 origin-bottom"
            :style="{
              transform: `rotate(${itemAngle(i, ring.items.length, ring.offsetDeg ?? 0)}deg)`,
            }"
          >
            <div
              class="orbit-badge absolute left-0 top-0 flex -translate-x-1/2 -translate-y-1/2 items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-1 font-mono text-[9px] uppercase tracking-wide backdrop-blur-md transition-[transform,box-shadow,border-color] duration-300 sm:text-[10px]"
              :class="ORBIT_TONE_CLASS[ring.tone] || ORBIT_TONE_CLASS.cyan"
              :style="badgeSpinStyle(ring)"
            >
              <span
                v-if="item.color"
                class="h-1.5 w-1.5 shrink-0 rounded-full"
                :style="{ background: item.color, boxShadow: `0 0 6px ${item.color}` }"
              />
              {{ item.label }}
            </div>
          </div>
        </div>
      </div>

      <!-- Vòng pulse ngoài cùng -->
      <div
        class="absolute inset-0 rounded-full border border-white/[0.06]"
        :class="reduced ? '' : 'animate-cn-glow'"
      />
    </div>
  </div>
</template>

<style scoped>
.orbit-badge {
    will-change: transform;
}

@media (prefers-reduced-motion: reduce) {
    .orbit-badge {
        animation: none !important;
    }
}
</style>
