<script setup>
/**
 * Chọn enum (trạng thái, ưu tiên…) bằng chip — dùng trong modal sửa task.
 */
defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    ariaLabel: { type: String, default: 'Chọn giá trị' },
});

const emit = defineEmits(['update:modelValue']);

const dotClass = {
    slate: 'bg-slate-400',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
};
</script>

<template>
  <div
    class="flex flex-wrap gap-1.5"
    role="radiogroup"
    :aria-label="ariaLabel"
  >
    <button
      v-for="o in options"
      :key="o.value"
      type="button"
      role="radio"
      :aria-checked="modelValue === o.value"
      class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition"
      :class="modelValue === o.value
        ? 'border-brand bg-brand/10 text-brand shadow-sm ring-2 ring-brand/25 dark:bg-brand/15'
        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500'"
      @click="emit('update:modelValue', o.value)"
    >
      <span
        class="h-2 w-2 shrink-0 rounded-full"
        :class="dotClass[o.color] || dotClass.slate"
      />
      {{ o.label }}
    </button>
  </div>
</template>
