<script setup>
import GlassCard from './GlassCard.vue';
import ScanLineOverlay from './ScanLineOverlay.vue';

defineProps({
    tilt: { type: Boolean, default: true },
    glow: { type: Boolean, default: false },
    padded: { type: Boolean, default: true },
    tone: { type: String, default: 'cyan' },
});
</script>

<template>
  <GlassCard
    :tilt="tilt"
    :glow="glow"
    :padded="padded"
    class="group/holo"
  >
    <template #overlay>
      <!-- viền conic xoay (dùng @property --cn-angle) -->
      <span
        class="pointer-events-none absolute inset-0 rounded-2xl opacity-50 animate-cn-border-rotate"
        style="
          padding: 1px;
          background: conic-gradient(from var(--cn-angle), transparent 0deg, rgba(34,211,238,0.6) 80deg, rgba(167,139,250,0.5) 170deg, transparent 300deg);
          -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
          -webkit-mask-composite: xor;
          mask-composite: exclude;
        "
        aria-hidden="true"
      />
      <ScanLineOverlay
        trigger="hover"
        :tone="tone"
      />
    </template>
    <div class="relative animate-cn-hologram">
      <slot />
    </div>
  </GlassCard>
</template>
