<script setup>
import { ref, watch, computed } from 'vue';
import { formatVndNumber, parseVndNumber, formatVndWords } from '../composables/useContractFormat.js';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    placeholder: { type: String, default: '0' },
    id: { type: String, default: undefined },
    /** Hiện dòng đọc chữ dưới input */
    hint: { type: Boolean, default: true },
    suffix: { type: String, default: 'VNĐ' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const display = ref('');

function syncFromModel(val) {
    const n = parseVndNumber(val);
    display.value = n === null ? '' : formatVndNumber(n);
}
syncFromModel(props.modelValue);

watch(() => props.modelValue, (val) => {
    // Chỉ đồng bộ lại khi giá trị số khác (tránh nhảy con trỏ khi đang gõ).
    if (parseVndNumber(val) !== parseVndNumber(display.value)) {
        syncFromModel(val);
    }
});

function onInput(e) {
    const num = parseVndNumber(e.target.value);
    display.value = num === null ? '' : formatVndNumber(num);
    emit('update:modelValue', num);
}

const words = computed(() => {
    const n = parseVndNumber(display.value);
    return n ? `${formatVndWords(n)} ${props.suffix}` : '';
});
</script>

<template>
  <div>
    <div class="relative">
      <input
        :id="id"
        :value="display"
        inputmode="numeric"
        :placeholder="placeholder"
        :disabled="disabled"
        class="input pr-12 text-right tabular-nums"
        @input="onInput"
      >
      <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">{{ suffix }}</span>
    </div>
    <p
      v-if="hint && words"
      class="mt-1 text-[11px] text-slate-500"
    >
      {{ words }}
    </p>
  </div>
</template>
