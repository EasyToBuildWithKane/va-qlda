<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    src: { type: String, default: null },
    size: { type: String, default: 'md' }, // sm | md | lg
});

const sizes = {
    sm: 'h-9 w-9 text-[11px]',
    md: 'h-12 w-12 text-sm',
    lg: 'h-16 w-16 text-base',
};

const initials = computed(() => {
    const parts = (props.name || '').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    return parts.slice(-2).map((p) => p[0].toUpperCase()).join('');
});
</script>

<template>
  <span
    class="grid shrink-0 place-items-center overflow-hidden rounded-full bg-gradient-to-br from-brand/70 to-[#ff4d8d]/70 font-semibold text-white ring-2 ring-white/10"
    :class="sizes[size]"
  >
    <img
      v-if="src"
      :src="src"
      :alt="name"
      class="h-full w-full object-cover"
      loading="lazy"
    >
    <span v-else>{{ initials }}</span>
  </span>
</template>
