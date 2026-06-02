<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    options: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] },
    countLabel: { type: String, default: 'đã chọn' },
});

const emit = defineEmits(['update:modelValue']);

const selected = computed(() => props.modelValue ?? []);

const isOn = (v) => selected.value.includes(v);

const toggle = (v) => {
    const next = isOn(v) ? selected.value.filter((x) => x !== v) : [...selected.value, v];
    emit('update:modelValue', next);
};
</script>

<template>
  <div>
    <div class="mb-2 flex items-center gap-2">
      <span
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold"
        :class="selected.length ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-400'"
      >
        {{ selected.length }} {{ countLabel }}
      </span>
    </div>
    <div class="flex flex-wrap gap-2">
      <button
        v-for="opt in options"
        :key="opt.value"
        type="button"
        :aria-pressed="isOn(opt.value)"
        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition"
        :class="isOn(opt.value)
          ? 'border-brand bg-brand text-white shadow-sm'
          : 'border-slate-200 bg-white text-slate-600 hover:border-brand-300 hover:bg-slate-50'"
        @click="toggle(opt.value)"
      >
        <AppIcon
          v-if="isOn(opt.value)"
          name="check"
          :size="14"
        />
        {{ opt.label }}
      </button>
      <p
        v-if="options.length === 0"
        class="text-sm text-slate-400"
      >
        Không có lựa chọn.
      </p>
    </div>
  </div>
</template>
