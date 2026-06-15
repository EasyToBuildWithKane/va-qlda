<script setup>
/**
 * Lớp nền trang trí thương hiệu VAS — watermark kỳ lân / huy hiệu.
 */
import { congngheBrand } from './congngheBrand.js';
defineProps({
    variant: {
        type: String,
        default: 'dragon',
        validator: (v) => ['dragon', 'badge', 'both'].includes(v),
    },
    /** left | right | center */
    align: {
        type: String,
        default: 'right',
    },
    opacityClass: {
        type: String,
        default: 'opacity-[0.07]',
    },
});
</script>

<template>
  <div
    class="pointer-events-none absolute inset-0 overflow-hidden"
    aria-hidden="true"
  >
    <img
      v-if="variant === 'dragon' || variant === 'both'"
      :src="congngheBrand.dragonSilhouette"
      alt=""
      class="absolute h-auto max-h-[min(520px,85vh)] w-auto max-w-[min(420px,70vw)] select-none object-contain"
      :class="[
        opacityClass,
        align === 'right' && 'right-0 top-1/2 -translate-y-1/2 translate-x-[18%]',
        align === 'left' && 'left-0 top-1/2 -translate-y-1/2 -translate-x-[18%]',
        align === 'center' && 'left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 scale-110',
      ]"
      loading="lazy"
      decoding="async"
    >
    <img
      v-if="variant === 'badge' || variant === 'both'"
      :src="congngheBrand.badgeCircle"
      alt=""
      class="absolute h-48 w-48 select-none object-contain sm:h-64 sm:w-64"
      :class="[
        opacityClass,
        align === 'left' && 'left-8 top-8',
        align === 'right' && 'right-8 bottom-8',
        align === 'center' && 'left-1/2 top-8 -translate-x-1/2',
      ]"
      loading="lazy"
      decoding="async"
    >
  </div>
</template>
