<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useMagnetic } from './motion.js';

const props = defineProps({
    href: { type: String, default: null },
    inertia: { type: Boolean, default: false },
    variant: { type: String, default: 'primary' }, // primary | ghost
});

const { el, style } = useMagnetic({ strength: 0.3 });

const base =
    'group/btn relative inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold transition-[transform,box-shadow] duration-200 will-change-transform';

const variants = {
    primary:
        'text-white bg-gradient-to-r from-brand via-[#c12a5b] to-[#ff4d8d] shadow-[0_10px_30px_-8px_rgba(255,77,141,0.55)] hover:shadow-[0_14px_40px_-8px_rgba(255,77,141,0.7)]',
    ghost:
        'text-white/85 border border-white/15 bg-white/5 backdrop-blur hover:bg-white/10 hover:text-white',
};

const cls = computed(() => `${base} ${variants[props.variant] ?? variants.primary}`);
const tag = computed(() => (props.href ? (props.inertia ? Link : 'a') : 'button'));
</script>

<template>
  <component
    :is="tag"
    :ref="el"
    :style="style"
    :href="href || undefined"
    :type="href ? undefined : 'button'"
    :class="cls"
  >
    <span class="relative z-10 inline-flex items-center gap-2">
      <slot />
    </span>
    <span
      v-if="variant === 'primary'"
      class="pointer-events-none absolute inset-0 rounded-full opacity-0 transition-opacity duration-300 group-hover/btn:opacity-100"
      style="background: linear-gradient(110deg, transparent 20%, rgba(255,255,255,0.35) 50%, transparent 80%); background-size: 200% 100%;"
    />
  </component>
</template>
