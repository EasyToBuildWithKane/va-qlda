<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

import { hours as fmtHours } from '@/composables/useFormat';

const props = defineProps({
    summary: { type: Object, required: true },
    monthly: { type: Object, default: () => ({}) },
});

const cards = computed(() => [
    {
        key: 'students',
        label: 'Học viên',
        value: props.summary.students_distinct ?? 0,
        tone: 'emerald',
        icon: 'people',
        sub: 'Theo khóa (tên hoặc nhân sự)',
        interactive: false,
    },
    {
        key: 'active',
        label: 'Khóa đang diễn ra',
        value: props.summary.courses_active ?? 0,
        tone: 'brand',
        icon: 'rocket',
        sub: `/ ${props.summary.courses_total ?? 0} tổng`,
        interactive: false,
    },
    {
        key: 'sessions',
        label: 'Tổng buổi học',
        value: props.summary.sessions_total ?? 0,
        tone: 'sky',
        icon: 'calendar',
        sub: `${props.monthly.sessions_completed ?? 0} hoàn thành tháng này`,
        interactive: false,
    },
    {
        key: 'hours',
        label: 'Tổng giờ đào tạo',
        value: fmtHours(props.summary.hours_total ?? 0),
        tone: 'violet',
        icon: 'clock',
        sub: `${fmtHours(props.monthly.hours_total ?? 0)} trong tháng`,
        interactive: false,
    },
]);
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê coaching"
    heading="Tổng quan đào tạo & coaching"
    hint=""
    grid-class="grid-cols-2 gap-3 sm:grid-cols-2 xl:grid-cols-4"
    :cards="cards"
  />
</template>
