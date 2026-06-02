<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    title: { type: String, default: 'Không có dữ liệu' },
    description: { type: String, default: null },
    icon: { type: String, default: 'inbox' },
    action: { type: String, default: null },
});

defineEmits(['action']);
</script>

<template>
  <div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="mb-4 grid h-16 w-16 place-items-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
      <AppIcon
        :name="icon"
        :size="32"
      />
    </div>
    <p class="text-base font-semibold text-slate-700 dark:text-slate-200">
      {{ title }}
    </p>
    <p
      v-if="description"
      class="mt-1 text-sm text-slate-400"
    >
      {{ description }}
    </p>
    <button
      v-if="action"
      type="button"
      class="btn-primary mt-6"
      @click="$emit('action')"
    >
      {{ action }}
    </button>
    <slot />
  </div>
</template>
