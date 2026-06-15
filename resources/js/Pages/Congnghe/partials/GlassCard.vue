<script setup>
import { computed } from 'vue';
import { useTilt } from './motion.js';

const props = defineProps({
    tilt: { type: Boolean, default: false },
    glow: { type: Boolean, default: true },
    padded: { type: Boolean, default: true },
    as: { type: String, default: 'div' },
});

const { el, style } = useTilt({ max: 6, scale: 1.015 });
const bindEl = computed(() => (props.tilt ? el : undefined));
const bindStyle = computed(() => (props.tilt ? style.value : undefined));
</script>

<template>
  <component
    :is="as"
    :ref="bindEl"
    :style="bindStyle"
    class="group/glass relative overflow-hidden rounded-2xl border border-white/10 bg-white/[0.035] shadow-[0_8px_40px_-12px_rgba(0,0,0,0.5)] backdrop-blur-xl transition-[transform,border-color,background-color] duration-300 will-change-transform hover:border-white/20 hover:bg-white/[0.055]"
    :class="padded && 'p-6'"
  >
    <!-- gradient glow border on hover -->
    <span
      v-if="glow"
      class="pointer-events-none absolute inset-0 rounded-2xl opacity-0 transition-opacity duration-300 group-hover/glass:opacity-100"
      style="
        padding: 1px;
        background: linear-gradient(135deg, rgba(255,77,141,0.55), rgba(124,58,237,0.4), transparent 60%);
        -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
      "
    />
    <!-- glare following pointer -->
    <span
      v-if="tilt"
      class="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover/glass:opacity-100"
      style="background: radial-gradient(220px circle at var(--cn-mx,50%) var(--cn-my,50%), rgba(255,255,255,0.10), transparent 60%);"
    />
    <div class="relative">
      <slot />
    </div>
  </component>
</template>
