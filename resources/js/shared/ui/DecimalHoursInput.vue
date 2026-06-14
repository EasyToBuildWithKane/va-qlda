<script setup>
import { computed, ref, watch } from 'vue';
import { hours as formatHours } from '@/composables/useFormat';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    placeholder: { type: String, default: '0' },
    id: { type: String, default: null },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function parseDisplay(raw) {
    const cleaned = String(raw).replace(/\s/g, '').replace(',', '.');
    if (cleaned === '' || cleaned === '.') return null;
    const n = Number(cleaned);
    return Number.isFinite(n) ? n : null;
}

function fmt(n) {
    if (n === null || n === '' || Number.isNaN(Number(n))) return '';
    return Number(n).toLocaleString('vi-VN', { maximumFractionDigits: 2 });
}

const display = ref(fmt(props.modelValue));

watch(
    () => props.modelValue,
    (v) => {
        const parsed = parseDisplay(display.value);
        if (parsed !== v && !(parsed == null && (v === null || v === ''))) {
            display.value = fmt(v);
        }
    },
);

const onInput = (e) => {
    let raw = e.target.value.replace(/[^\d,.]/g, '');
    const parts = raw.split(/[,.]/);
    if (parts.length > 2) {
        raw = `${parts[0]},${parts.slice(1).join('')}`;
    }
    display.value = raw;
    emit('update:modelValue', parseDisplay(raw));
};

const summary = computed(() => {
    const n = parseDisplay(display.value);
    if (n == null || n <= 0) return '';
    return formatHours(n);
});
</script>

<template>
  <div>
    <div class="relative">
      <input
        :id="id"
        :value="display"
        type="text"
        inputmode="decimal"
        :placeholder="placeholder"
        class="input pr-12 text-right font-medium tabular-nums"
        :class="invalid ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
        @input="onInput"
      >
      <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">giờ</span>
    </div>
    <p
      v-if="summary"
      class="mt-1 text-xs font-medium text-brand"
    >
      {{ summary }}
    </p>
  </div>
</template>
