<script setup>
import { computed } from 'vue';
import { HERO_TECH_ORBITS, ORBIT_TONE_CLASS } from './congngheHeroTechOrbits.js';
import { prefersReducedMotionNow } from './motion.js';

const props = defineProps({
    active: { type: Boolean, default: true },
    /** Ghi đè vòng quỹ đạo (vd. từ CMS sau này) */
    orbits: { type: Array, default: null },
});

const reduced = prefersReducedMotionNow();

const rings = computed(() => (props.orbits?.length ? props.orbits : HERO_TECH_ORBITS));

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
        <div
          v-if="!reduced && ringIndex === 0"
          class="absolute inset-[-1px] rounded-full opacity-40 animate-cn-border-rotate"
          style="
            padding: 1px;
            background: conic-gradient(from var(--cn-angle), transparent 0deg, rgba(34,211,238,0.45) 70deg, rgba(255,77,141,0.35) 200deg, transparent 300deg);
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
          "
        />

        <!-- Hành tinh nhỏ trên quỹ đạo -->
        <div
          class="absolute inset-0"
          :style="ringSpinStyle(ring)"
        >
          <div
            v-for="(label, i) in (ring.items ?? [])"
            :key="`${label}-${i}`"
            class="absolute left-1/2 top-1/2 h-1/2 w-0 origin-bottom"
            :style="{
              transform: `rotate(${itemAngle(i, ring.items.length, ring.offsetDeg ?? 0)}deg)`,
            }"
          >
            <div
              class="orbit-badge absolute bottom-0 left-0 -translate-x-1/2 translate-y-1/2 whitespace-nowrap rounded-full border px-2.5 py-1 font-mono text-[9px] uppercase tracking-wide backdrop-blur-md transition-[transform,box-shadow,border-color] duration-300 sm:text-[10px]"
              :class="ORBIT_TONE_CLASS[ring.tone] || ORBIT_TONE_CLASS.cyan"
              :style="badgeSpinStyle(ring)"
            >
              {{ label }}
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
