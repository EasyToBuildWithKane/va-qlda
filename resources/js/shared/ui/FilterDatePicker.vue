<script setup>
import { computed } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import { vi } from 'date-fns/locale';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    id: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    /** ISO `yyyy-mm-dd` */
    minDate: { type: String, default: null },
    maxDate: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

function isoToDate(iso) {
    if (!iso) return null;
    const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return null;
    return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
}

const minD = computed(() => isoToDate(props.minDate));
const maxD = computed(() => isoToDate(props.maxDate));

const pickerUi = {
    input: 'input h-10 w-full min-w-0 text-sm tabular-nums',
};

function onUpdate(val) {
    emit('update:modelValue', val || '');
}
</script>

<template>
  <div class="va-filter-date-picker min-w-0 w-full">
    <VueDatePicker
      :uid="id"
      :model-value="modelValue || null"
      model-type="yyyy-MM-dd"
      format="dd/MM/yyyy"
      preview-format="dd/MM/yyyy"
      :locale="vi"
      :placeholder="placeholder"
      :disabled="disabled"
      :min-date="minD"
      :max-date="maxD"
      :enable-time-picker="false"
      :teleport="true"
      auto-apply
      :clearable="true"
      :ui="pickerUi"
      week-start="1"
      @update:model-value="onUpdate"
    />
  </div>
</template>

<style scoped>
.va-filter-date-picker :deep(.dp__main) {
    width: 100%;
}

.va-filter-date-picker :deep(.dp__input_wrap) {
    width: 100%;
}

.va-filter-date-picker :deep(.dp__input) {
    min-height: 2.5rem;
}

.va-filter-date-picker :deep(.dp__active_date),
.va-filter-date-picker :deep(.dp__range_start),
.va-filter-date-picker :deep(.dp__range_end) {
    background: #9a0036;
}

.va-filter-date-picker :deep(.dp__today) {
    border-color: rgb(154 0 54 / 0.45);
}
</style>
