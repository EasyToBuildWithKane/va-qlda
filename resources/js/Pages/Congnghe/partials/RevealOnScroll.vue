<script setup>
import { computed } from 'vue';
import { useInView, prefersReducedMotionNow } from './motion.js';

const props = defineProps({
    as: { type: String, default: 'div' },
    delay: { type: Number, default: 0 },
    // 'up' | 'left' | 'right' | 'fade'
    variant: { type: String, default: 'up' },
    once: { type: Boolean, default: true },
    threshold: { type: Number, default: 0.15 },
});

const { target, shown } = useInView({ threshold: props.threshold, once: props.once });
const reduced = prefersReducedMotionNow();
const visible = computed(() => reduced || shown.value);

const hiddenClass = {
    up: 'translate-y-7 opacity-0',
    left: '-translate-x-7 opacity-0',
    right: 'translate-x-7 opacity-0',
    fade: 'opacity-0',
};
</script>

<template>
  <component
    :is="as"
    ref="target"
    class="transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] will-change-transform motion-reduce:transition-none"
    :class="visible ? 'translate-x-0 translate-y-0 opacity-100' : (hiddenClass[variant] || hiddenClass.up)"
    :style="{ transitionDelay: visible ? `${delay}ms` : '0ms' }"
  >
    <slot />
  </component>
</template>
