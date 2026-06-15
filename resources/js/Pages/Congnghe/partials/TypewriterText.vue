<script setup>
import { useTypewriter } from './motion.js';

const props = defineProps({
    text: { type: String, default: '' },
    speed: { type: Number, default: 28 },
    startDelay: { type: Number, default: 0 },
    active: { type: Boolean, default: true },
    caret: { type: Boolean, default: true },
    tag: { type: String, default: 'span' },
});

const { display, done } = useTypewriter(() => props.text, {
    speed: props.speed,
    startDelay: props.startDelay,
    active: () => props.active,
});
</script>

<template>
  <component :is="tag">
    <span class="whitespace-pre-wrap">{{ display }}</span><span
      v-if="caret && !done"
      class="ml-0.5 inline-block h-[1em] w-[2px] -translate-y-[0.05em] animate-cn-caret-blink bg-current align-middle"
      aria-hidden="true"
    />
  </component>
</template>
