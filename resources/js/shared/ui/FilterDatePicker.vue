<script setup>
import { computed } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import { vi as dateFnsVi } from 'date-fns/locale/vi';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    id: { type: String, default: null },
    name: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    /** ISO `yyyy-mm-dd` */
    minDate: { type: String, default: null },
    maxDate: { type: String, default: null },
    clearable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const inputAriaLabel = computed(() => props.placeholder || 'Chọn ngày');

const ariaLabels = computed(() => ({
    input: inputAriaLabel.value,
}));

const inputAttrs = computed(() => ({
    id: props.id || undefined,
    name: props.name || props.id || undefined,
    clearable: props.clearable,
}));

function isoToDate(iso) {
    if (!iso) return null;
    const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return null;
    return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
}

const minD = computed(() => isoToDate(props.minDate));
const maxD = computed(() => isoToDate(props.maxDate));

/**
 * ui.input = mảng class (VueDatePicker v14).
 * Không dùng `.input` / `px-*` — đè padding & làm icon lịch đè placeholder.
 */
const pickerUi = {
    input: [
        'h-10',
        'w-full',
        'min-w-0',
        'rounded-input',
        'border',
        'border-slate-300',
        'bg-white',
        'text-sm',
        'tabular-nums',
        'text-slate-800',
        'shadow-sm',
        'focus:border-brand',
        'focus:outline-none',
        'focus:ring-1',
        'focus:ring-brand/30',
    ],
};

function formatDisplay(date) {
    if (!date) return '';
    const raw = Array.isArray(date) ? date[0] : date;
    if (!raw) return '';
    const d = raw instanceof Date ? raw : new Date(raw);
    if (Number.isNaN(d.getTime())) return '';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');

    return `${dd}/${mm}/${d.getFullYear()}`;
}

/** VueDatePicker v14: format/preview-format → formats.input|preview */
const pickerFormats = {
    input: formatDisplay,
    preview: formatDisplay,
};

/** VueDatePicker v14: enable-time-picker → timeConfig */
const timeConfig = { enableTimePicker: false };

function onUpdate(val) {
    emit('update:modelValue', val || '');
}
</script>

<template>
  <div class="va-filter-date-picker min-w-0 w-full">
    <VueDatePicker
      :input-attrs="inputAttrs"
      :aria-labels="ariaLabels"
      :model-value="modelValue || null"
      model-type="yyyy-MM-dd"
      :formats="pickerFormats"
      :locale="dateFnsVi"
      :placeholder="placeholder"
      :disabled="disabled"
      :min-date="minD"
      :max-date="maxD"
      :time-config="timeConfig"
      :teleport="true"
      auto-apply
      :ui="pickerUi"
      :week-start="1"
      @update:model-value="onUpdate"
    />
  </div>
</template>

<style scoped>
/* VueDatePicker v14: class dp--* (legacy dp__* giữ tương thích) */
.va-filter-date-picker {
    --dp-font-size: 0.875rem;
    --dp-border-radius: 0.5rem;
    --dp-border-color: #cbd5e1;
    --dp-border-color-hover: #94a3b8;
    --dp-border-color-focus: #9a0036;
    --dp-primary-color: #9a0036;
    --dp-primary-text-color: #fff;
    --dp-input-icon-padding: 2.75rem;
}

.va-filter-date-picker :deep(.dp--main),
.va-filter-date-picker :deep(.dp__main) {
    width: 100%;
}

.va-filter-date-picker :deep(.dp--input-wrap),
.va-filter-date-picker :deep(.dp__input_wrap) {
    width: 100%;
}

/*
 * Longhand + !important: tránh shorthand `padding` đè mất
 * `.dp--input-icon-pad { padding-inline-start }` của lib (icon lịch trái).
 */
.va-filter-date-picker :deep(input.dp--input),
.va-filter-date-picker :deep(input.dp__input) {
    box-sizing: border-box;
    min-height: 2.5rem;
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
    padding-right: 2.25rem !important;
    padding-left: 2.75rem !important;
}

.va-filter-date-picker :deep(input.dp--input-icon-pad),
.va-filter-date-picker :deep(input.dp--input.dp--input-icon-pad) {
    padding-left: 2.75rem !important;
    padding-inline-start: 2.75rem !important;
}

.va-filter-date-picker :deep(.dp--input-icons),
.va-filter-date-picker :deep(.dp__input_icon) {
    color: #94a3b8;
}

.va-filter-date-picker :deep(.dp--active),
.va-filter-date-picker :deep(.dp__active_date),
.va-filter-date-picker :deep(.dp__range_start),
.va-filter-date-picker :deep(.dp__range_end) {
    background: #9a0036;
}

.va-filter-date-picker :deep(.dp--today),
.va-filter-date-picker :deep(.dp__today) {
    border-color: rgb(154 0 54 / 0.45);
}
</style>
