<script setup>
defineProps({
    // 'hover' (theo group-hover) | 'always' | 'manual' (theo prop active)
    trigger: { type: String, default: 'hover' },
    active: { type: Boolean, default: false },
    tone: { type: String, default: 'cyan' },
});

const toneColor = {
    cyan: 'rgba(34,211,238,0.7)',
    brand: 'rgba(255,77,141,0.7)',
    violet: 'rgba(167,139,250,0.7)',
    emerald: 'rgba(52,211,153,0.7)',
};
</script>

<template>
  <span
    class="pointer-events-none absolute inset-0 overflow-hidden rounded-[inherit]"
    :class="[
      trigger === 'always' ? 'opacity-100' : '',
      trigger === 'manual' ? (active ? 'opacity-100' : 'opacity-0') : '',
      trigger === 'hover'
        ? 'opacity-0 transition-opacity duration-300 group-hover/glass:opacity-100 group-hover/holo:opacity-100 group-hover:opacity-100'
        : '',
    ]"
    aria-hidden="true"
  >
    <span
      class="absolute inset-x-0 top-0 h-1/3 animate-cn-scan-line"
      :style="{
        background: `linear-gradient(to bottom, transparent, ${toneColor[tone] || toneColor.cyan}, transparent)`,
        filter: 'blur(1px)',
      }"
    />
  </span>
</template>
