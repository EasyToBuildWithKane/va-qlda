<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    recalling: { type: Boolean, default: false },
    label: { type: String, default: 'Rút lại' },
    busyLabel: { type: String, default: 'Đang rút lại…' },
    block: { type: Boolean, default: false },
});

defineEmits(['recall']);
</script>

<template>
  <button
    type="button"
    class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-btn border border-amber-300 bg-amber-50 px-4 text-sm font-semibold text-amber-700 transition hover:border-amber-400 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-60"
    :class="block ? 'w-full sm:w-auto' : ''"
    :disabled="recalling"
    @click="$emit('recall')"
  >
    <AppIcon
      name="back"
      :size="16"
    />
    {{ recalling ? busyLabel : label }}
  </button>
</template>
