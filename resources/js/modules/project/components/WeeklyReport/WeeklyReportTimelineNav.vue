<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';

const props = defineProps({
    periodStart: { type: String, default: '' },
    periodEnd: { type: String, default: '' },
});

const emit = defineEmits(['update-period']);

function onStartChange(iso) {
    let end = props.periodEnd;
    if (iso && end && iso > end) end = iso;
    emit('update-period', { start: iso || '', end });
}

function onEndChange(iso) {
    let start = props.periodStart;
    if (iso && start && iso < start) start = iso;
    emit('update-period', { start, end: iso || '' });
}
</script>

<template>
  <div
    class="wr-period flex h-10 w-full min-w-0 shrink-0 items-stretch overflow-hidden rounded-input border border-slate-200 bg-white shadow-sm sm:w-[26rem] dark:border-slate-700 dark:bg-slate-900"
    role="group"
    aria-label="Kỳ báo cáo"
  >
    <span
      class="flex shrink-0 items-center border-r border-slate-100 px-2.5 text-slate-400 dark:border-slate-800"
      aria-hidden="true"
    >
      <AppIcon
        name="calendar"
        :size="15"
      />
    </span>
    <div class="wr-period__picker min-w-0 flex-1">
      <FilterDatePicker
        :model-value="periodStart"
        placeholder="Từ ngày"
        :max-date="periodEnd || null"
        :clearable="false"
        @update:model-value="onStartChange"
      />
    </div>
    <span
      class="flex shrink-0 items-center px-0.5 text-xs text-slate-300 dark:text-slate-600"
      aria-hidden="true"
    >→</span>
    <div class="wr-period__picker min-w-0 flex-1">
      <FilterDatePicker
        :model-value="periodEnd"
        placeholder="Đến ngày"
        :min-date="periodStart || null"
        :clearable="false"
        @update:model-value="onEndChange"
      />
    </div>
  </div>
</template>

<style scoped>
.wr-period:focus-within {
    border-color: #9a0036;
    box-shadow: 0 0 0 1px rgb(154 0 54 / 0.3);
}

.wr-period :deep(.dp--input-icon),
.wr-period :deep(.dp--input-icons),
.wr-period :deep(.dp__input_icon),
.wr-period :deep(.dp--clear-btn) {
    display: none;
}

.wr-period :deep(input.dp--input),
.wr-period :deep(input.dp__input),
.wr-period :deep(input.dp--input-icon-pad) {
    border: 0 !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    padding-left: 0.5rem !important;
    padding-inline-start: 0.5rem !important;
    padding-right: 0.625rem !important;
}

.wr-period :deep(input.dp--input:focus),
.wr-period :deep(input.dp__input:focus) {
    border-color: transparent !important;
    box-shadow: none !important;
    outline: none !important;
}
</style>
