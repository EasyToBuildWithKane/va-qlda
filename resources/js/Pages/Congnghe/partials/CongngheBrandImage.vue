<script setup>
/**
 * Ảnh thương hiệu / mascot có nền đen trong file — blend screen trên nền tối landing.
 * `isolatedCutout`: nền cố định + isolation (header backdrop-blur không blend đúng nếu không bọc).
 */
defineOptions({ inheritAttrs: false });

defineProps({
    src: { type: String, required: true },
    alt: { type: String, default: '' },
    /** Tắt khi ảnh đã có alpha (logo nền sáng, ảnh dự án). */
    cutout: { type: Boolean, default: true },
    /** Bọc nền khớp header — xóa hộp đen trên navbar cố định. */
    isolatedCutout: { type: Boolean, default: false },
    /** Màu nền blend (mặc định header /congnghe). */
    surfaceColor: { type: String, default: '#070912' },
});
</script>

<template>
  <span
    v-if="isolatedCutout"
    class="cn-brand-mark inline-flex shrink-0 items-center justify-center overflow-hidden"
    :style="{ backgroundColor: surfaceColor }"
    v-bind="$attrs"
  >
    <img
      :src="src"
      :alt="alt"
      class="h-full w-full object-contain"
      :class="cutout ? 'cn-brand-cutout' : null"
      decoding="async"
    >
  </span>
  <img
    v-else
    :src="src"
    :alt="alt"
    class="object-contain"
    :class="cutout ? 'cn-brand-cutout' : null"
    decoding="async"
    v-bind="$attrs"
  >
</template>

<style>
.cn-brand-mark {
    isolation: isolate;
}

.cn-brand-cutout {
    mix-blend-mode: screen;
}
</style>
