<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    /** single | bulk */
    mode: { type: String, default: 'single' },
});

const emit = defineEmits(['update:mode']);

const tabs = [
    { id: 'single', label: 'Một vướng mắc', icon: 'blockers' },
    { id: 'bulk', label: 'Nhiều vướng mắc', icon: 'template' },
];
</script>

<template>
  <div
    class="mb-3 flex w-full rounded-lg bg-slate-100/90 p-0.5"
    role="tablist"
    aria-label="Cách ghi nhận"
  >
    <button
      v-for="tab in tabs"
      :key="tab.id"
      type="button"
      role="tab"
      :aria-selected="mode === tab.id"
      class="flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-xs font-semibold transition sm:flex-none sm:px-4"
      :class="mode === tab.id
        ? 'bg-white text-brand shadow-sm ring-1 ring-slate-200/80'
        : 'text-slate-600 hover:text-slate-800'"
      @click="emit('update:mode', tab.id)"
    >
      <AppIcon
        :name="tab.icon"
        :size="16"
        class="shrink-0"
        :class="mode === tab.id ? 'text-brand' : 'text-slate-400'"
      />
      {{ tab.label }}
    </button>
  </div>
</template>
