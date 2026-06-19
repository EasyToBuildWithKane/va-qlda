<script setup>
/**
 * Dải KPI tổng quan hệ thống cho trang /dashboard (Hub).
 * Bọc shared KpiSummaryStrip — thẻ tĩnh (informational), có trend.
 * Tham chiếu rule: .cursor/rules/kpi-summary-strip.mdc
 */
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

defineProps({
    summary: { type: Array, default: () => [] },
});
</script>

<template>
  <KpiSummaryStrip
    :cards="summary"
    eyebrow="Thống kê"
    heading="Chỉ số vận hành"
    hint="Số liệu toàn hệ thống — cập nhật theo thời gian thực"
    aria-label="Chỉ số tổng quan hệ thống"
    grid-class="grid-cols-2 sm:grid-cols-3 xl:grid-cols-6"
  />
</template>
