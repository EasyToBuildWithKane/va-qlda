<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    /** single | bulk */
    mode: { type: String, default: 'single' },
});

const emit = defineEmits(['update:mode']);

const tabs = [
    {
        id: 'single',
        label: 'Một vướng mắc',
        hint: 'Đề + mô tả + nhiều ảnh — phù hợp từng case cụ thể',
        icon: 'blockers',
    },
    {
        id: 'bulk',
        label: 'Nhiều vướng mắc',
        hint: 'Mỗi hàng một đề + ảnh riêng — ghi nhanh nhiều case',
        icon: 'template',
        badge: 'Nhanh',
    },
];
</script>

<template>
  <div
    class="mb-4 grid gap-2 sm:grid-cols-2"
    role="tablist"
    aria-label="Cách ghi nhận"
  >
    <button
      v-for="tab in tabs"
      :key="tab.id"
      type="button"
      role="tab"
      :aria-selected="mode === tab.id"
      class="flex min-h-[3.75rem] flex-col items-start rounded-lg border px-3 py-2 text-left transition"
      :class="mode === tab.id
        ? 'border-brand/35 bg-brand-50/50 ring-1 ring-brand/25'
        : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'"
      @click="emit('update:mode', tab.id)"
    >
      <span class="flex w-full items-center gap-2">
        <AppIcon
          :name="tab.icon"
          :size="18"
          class="shrink-0"
          :class="mode === tab.id ? 'text-brand' : 'text-slate-400'"
        />
        <span
          class="text-sm font-semibold"
          :class="mode === tab.id ? 'text-brand' : 'text-slate-800'"
        >
          {{ tab.label }}
        </span>
        <span
          v-if="tab.badge"
          class="ml-auto rounded-full bg-brand/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand"
        >
          {{ tab.badge }}
        </span>
      </span>
      <span class="mt-0.5 pl-7 text-[11px] leading-snug text-slate-500">
        {{ tab.hint }}
      </span>
    </button>
  </div>
</template>
