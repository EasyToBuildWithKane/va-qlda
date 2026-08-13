<script setup>
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
    class="flex w-[min(100%,22.5rem)] shrink-0 items-center gap-2 sm:w-[22.5rem]"
    role="group"
    aria-label="Kỳ báo cáo"
  >
    <div class="min-w-0 flex-1">
      <FilterDatePicker
        :model-value="periodStart"
        placeholder="Từ ngày"
        @update:model-value="onStartChange"
      />
    </div>
    <div class="min-w-0 flex-1">
      <FilterDatePicker
        :model-value="periodEnd"
        placeholder="Đến ngày"
        @update:model-value="onEndChange"
      />
    </div>
  </div>
</template>
