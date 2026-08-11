<script setup>
import { computed, ref, watch } from 'vue';
import { dongToWords } from '@/composables/useFormat';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    placeholder: { type: String, default: '0' },
    id: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    wordsClass: { type: String, default: 'mt-1 text-xs italic text-slate-400' },
});

const emit = defineEmits(['update:modelValue']);

const grouper = new Intl.NumberFormat('vi-VN');

const fmt = (n) => (n === null || n === '' || Number.isNaN(Number(n)) ? '' : grouper.format(Math.round(Number(n))));

const display = ref(fmt(props.modelValue));

watch(
    () => props.modelValue,
    (v) => {
        const current = display.value.replace(/\D/g, '');
        if (current !== (v === null || v === '' ? '' : String(Math.round(Number(v))))) {
            display.value = fmt(v);
        }
    },
);

const onInput = (e) => {
    const digits = e.target.value.replace(/\D/g, '');
    display.value = digits ? grouper.format(Number(digits)) : '';
    emit('update:modelValue', digits ? Number(digits) : null);
};

const words = computed(() => dongToWords(props.modelValue));
</script>

<template>
  <div>
    <div class="relative">
      <input
        :id="id"
        :value="display"
        type="text"
        inputmode="numeric"
        :placeholder="placeholder"
        class="input h-10 w-full pr-9 text-right text-sm font-medium tabular-nums"
        :class="invalid ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
        @input="onInput"
      >
      <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">₫</span>
    </div>
    <p
      v-if="words"
      :class="wordsClass"
    >
      {{ words }}
    </p>
  </div>
</template>
