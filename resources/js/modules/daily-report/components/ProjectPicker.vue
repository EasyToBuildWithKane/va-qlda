<script setup>
import { computed } from 'vue';

const props = defineProps({
    // [{ id, name }]
    modelValue: { type: Array, default: () => [] },
    // [{ id, name, code, color }]
    options: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

// Literal class strings so Tailwind's content scanner keeps them.
const palette = {
    brand: { dot: 'bg-brand', on: 'bg-brand text-white border-brand' },
    sky: { dot: 'bg-sky-500', on: 'bg-sky-500 text-white border-sky-500' },
    emerald: { dot: 'bg-emerald-500', on: 'bg-emerald-500 text-white border-emerald-500' },
    violet: { dot: 'bg-violet-500', on: 'bg-violet-500 text-white border-violet-500' },
    amber: { dot: 'bg-amber-500', on: 'bg-amber-500 text-white border-amber-500' },
    rose: { dot: 'bg-rose-500', on: 'bg-rose-500 text-white border-rose-500' },
    cyan: { dot: 'bg-cyan-500', on: 'bg-cyan-500 text-white border-cyan-500' },
    slate: { dot: 'bg-slate-500', on: 'bg-slate-600 text-white border-slate-600' },
};
const styleOf = (color) => palette[color] || palette.slate;

const selectedIds = computed(() => new Set(props.modelValue.map((p) => p.id)));
const isSelected = (opt) => selectedIds.value.has(opt.id);

const toggle = (opt) => {
    const next = isSelected(opt)
        ? props.modelValue.filter((p) => p.id !== opt.id)
        : [...props.modelValue, { id: opt.id, name: opt.name }];
    emit('update:modelValue', next);
};
</script>

<template>
  <div>
    <div class="flex flex-wrap gap-2">
      <button
        v-for="opt in options"
        :key="opt.id"
        type="button"
        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition"
        :class="isSelected(opt)
          ? styleOf(opt.color).on + ' shadow-sm'
          : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
        @click="toggle(opt)"
      >
        <span
          v-if="!isSelected(opt)"
          class="h-2 w-2 rounded-full"
          :class="styleOf(opt.color).dot"
        />
        <svg
          v-else
          class="h-3.5 w-3.5"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.3 3.29 6.8-6.8a1 1 0 0 1 1.4 0Z"
            clip-rule="evenodd"
          />
        </svg>
        {{ opt.name }}
      </button>
    </div>

    <p class="mt-2 text-xs text-slate-400">
      <span v-if="modelValue.length">Đã chọn {{ modelValue.length }} dự án trong ngày.</span>
      <span v-else>Chọn các dự án bạn đã làm việc hôm nay (có thể chọn nhiều).</span>
    </p>
  </div>
</template>
