<script setup>
/**
 * Ảnh thương hiệu / mascot có nền đen trong file — blend screen trên nền tối landing.
 */
defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: '' },
    /** Tắt khi ảnh đã có alpha (logo nền sáng, ảnh dự án). */
    cutout: { type: Boolean, default: true },
});
</script>

<template>
  <img
    :src="src"
    :alt="alt"
    class="object-contain"
    :class="cutout ? 'cn-brand-cutout' : null"
    decoding="async"
  >
</template>
