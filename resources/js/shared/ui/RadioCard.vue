<script setup>
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    value: { type: [String, Number], required: true },
    label: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const select = () => emit('update:modelValue', props.value);
</script>

<template>
  <button
    type="button"
    role="radio"
    :aria-checked="modelValue === value"
    class="group flex w-full gap-3 rounded-card border p-3.5 text-left transition"
    :class="[
      description ? 'items-start' : 'items-center',
      modelValue === value
        ? 'border-brand bg-brand-50 ring-1 ring-brand/40'
        : 'border-slate-200 bg-white hover:border-brand-300 hover:bg-slate-50',
    ]"
    @click="select"
  >
    <span
      class="grid h-9 w-9 shrink-0 place-items-center rounded-btn transition"
      :class="[
        description ? 'mt-0.5' : '',
        modelValue === value
          ? 'bg-brand text-white'
          : 'bg-slate-100 text-slate-500 group-hover:bg-brand-100 group-hover:text-brand',
      ]"
    >
      <AppIcon
        v-if="icon"
        :name="icon"
        :size="18"
      />
    </span>
    <span class="min-w-0 flex-1">
      <span class="text-sm font-semibold text-slate-800">{{ label }}</span>
      <span
        v-if="description"
        class="mt-0.5 block text-xs leading-snug text-slate-500"
      >{{ description }}</span>
    </span>
  </button>
</template>
