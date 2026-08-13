<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.filters.status === 'ready') return 'ready';
    if (props.filters.last_result === 'pass') return 'pass';
    if (props.filters.last_result === 'fail') return 'fail';
    if (props.filters.last_result === 'not_run' || props.filters.status === 'draft') return 'not_run';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total || 1;

    return [
        {
            key: 'total',
            label: 'Tổng',
            value: s.total ?? 0,
            tone: 'brand',
            icon: 'check-circle',
            sub: 'test case trên toàn hệ thống',
            interactive: false,
        },
        {
            key: 'ready',
            label: 'Sẵn sàng',
            value: s.ready ?? 0,
            tone: 'sky',
            icon: 'sprint',
            sub: `${Math.round(((s.ready ?? 0) / total) * 100)}% tổng test case`,
            progress: Math.round(((s.ready ?? 0) / (s.total || 1)) * 100),
            interactive: true,
            payload: { status: 'ready', last_result: '' },
        },
        {
            key: 'pass',
            label: 'Đạt',
            value: s.pass ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: 'Kết quả cuối: Đạt',
            interactive: true,
            payload: { last_result: 'pass', status: '' },
        },
        {
            key: 'fail',
            label: 'Không đạt',
            value: s.fail ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: 'Kết quả cuối: Không đạt',
            interactive: true,
            payload: { last_result: 'fail', status: '' },
        },
        {
            key: 'not_run',
            label: 'Chưa chạy',
            value: s.not_run ?? 0,
            tone: 'slate',
            icon: 'clock',
            sub: 'Chưa có kết quả thực thi',
            interactive: true,
            payload: { last_result: 'not_run', status: '' },
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê QA / Test case"
    heading="Tổng quan QA / Test case"
    hint="Thẻ viền nét đứt — bấm lọc nhanh"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
