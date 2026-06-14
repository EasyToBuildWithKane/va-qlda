<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    modelValue: { type: String, required: true },
    items: {
        type: Array,
        required: true,
        /** @type {{ key: string, label: string, icon?: string, title?: string }[]} */
    },
    ariaLabel: { type: String, default: 'Chọn chế độ' },
    /** Ẩn chữ, chỉ icon dưới breakpoint sm (toolbar hẹp) */
    iconOnlyBelowSm: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
  <div
    class="inline-flex h-10 shrink-0 items-stretch rounded-lg border border-slate-200 bg-slate-100/90 p-1 dark:border-slate-700 dark:bg-slate-800/60"
    role="group"
    :aria-label="ariaLabel"
  >
    <button
      v-for="item in items"
      :key="item.key"
      type="button"
      class="inline-flex min-w-0 items-center justify-center gap-1 rounded-md px-2 text-xs font-medium transition sm:gap-1.5 sm:px-3"
      :class="
        modelValue === item.key
          ? 'bg-white text-brand shadow-sm dark:bg-slate-700 dark:text-brand-100'
          : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
      "
      :title="item.title ?? item.label"
      @click="emit('update:modelValue', item.key)"
    >
      <AppIcon
        v-if="item.icon"
        :name="item.icon"
        :size="14"
      />
      <span
        class="whitespace-nowrap"
        :class="iconOnlyBelowSm ? 'hidden sm:inline' : ''"
      >{{ item.label }}</span>
    </button>
  </div>
</template>
