<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
    activeScope: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeStatus === 'active') return 'active';
    if (props.activeStatus === 'inactive') return 'inactive';
    if (props.activeScope === 'general') return 'general';
    if (props.activeScope === 'department') return 'department';
    if (!props.activeStatus && !props.activeScope) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng tiêu chí',
            value: total,
            tone: 'brand',
            icon: 'award',
            sub: total ? 'Tất cả phạm vi' : 'Chưa có tiêu chí',
            interactive: true,
            payload: { status: '', scope: '' },
        },
        {
            key: 'general',
            label: 'Tiêu chí chung',
            value: s.general ?? 0,
            tone: 'sky',
            icon: 'documents',
            sub: total ? `${pct(s.general ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.general ?? 0),
            interactive: true,
            payload: { status: '', scope: 'general' },
        },
        {
            key: 'department',
            label: 'Theo phòng ban',
            value: s.department ?? 0,
            tone: 'violet',
            icon: 'department',
            sub: total ? `${pct(s.department ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.department ?? 0),
            interactive: true,
            payload: { status: '', scope: 'department' },
        },
        {
            key: 'active',
            label: 'Đang hoạt động',
            value: s.active ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.active ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.active ?? 0),
            interactive: true,
            payload: { status: 'active', scope: '' },
        },
        {
            key: 'inactive',
            label: 'Ngưng hoạt động',
            value: s.inactive ?? 0,
            tone: 'slate',
            icon: 'close',
            sub: total ? `${pct(s.inactive ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.inactive ?? 0),
            interactive: true,
            payload: { status: 'inactive', scope: '' },
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê tiêu chí đánh giá"
    heading="Tổng quan tiêu chí đánh giá"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
