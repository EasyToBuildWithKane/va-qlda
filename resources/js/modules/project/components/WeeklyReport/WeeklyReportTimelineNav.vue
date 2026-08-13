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
  <div class="wr-scope">
    <div
      class="grid min-w-0 grid-cols-1 gap-2 sm:max-w-md sm:grid-cols-2"
      role="group"
      aria-label="Kỳ báo cáo"
    >
      <FilterDatePicker
        :model-value="periodStart"
        placeholder="Từ ngày"
        @update:model-value="onStartChange"
      />
      <FilterDatePicker
        :model-value="periodEnd"
        placeholder="Đến ngày"
        @update:model-value="onEndChange"
      />
    </div>
  </div>
</template>

<style scoped>
.wr-scope {
  background: #fff;
  padding: 0.875rem 1.25rem 1rem;
}
.dark .wr-scope {
  background: #0f172a;
}
</style>
