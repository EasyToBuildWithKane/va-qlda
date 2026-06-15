<script setup>
/**
 * Ảnh thương hiệu / mascot có nền đen trong file — blend screen trên nền tối landing.
 */
defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: '' },
    /** Tắt khi ảnh đã có alpha thật (hiếm). */
    cutout: { type: Boolean, default: true },
});
</script>

<template>
  <span
    class="inline-flex max-w-full shrink-0 leading-none"
    :class="cutout ? 'cn-brand-img-shell' : null"
  >
    <img
      :src="src"
      :alt="alt"
      class="block max-h-full max-w-full object-contain"
      :class="cutout ? 'cn-brand-cutout' : null"
      decoding="async"
    >
  </span>
</template>
