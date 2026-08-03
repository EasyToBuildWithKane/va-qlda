<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (['draft', 'active', 'closed'].includes(props.activeStatus)) return props.activeStatus;
    if (!props.activeStatus) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng phiếu',
            value: total,
            tone: 'brand',
            icon: 'clipboard-list',
            sub: total ? 'Tất cả phiếu đánh giá' : 'Chưa có phiếu',
            interactive: true,
            payload: { status: '' },
        },
        {
            key: 'draft',
            label: 'Nháp',
            value: s.draft ?? 0,
            tone: 'slate',
            icon: 'edit',
            sub: total ? `${pct(s.draft ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.draft ?? 0),
            interactive: true,
            payload: { status: 'draft' },
        },
        {
            key: 'active',
            label: 'Đang mở',
            value: s.active ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.active ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.active ?? 0),
            interactive: true,
            payload: { status: 'active' },
        },
        {
            key: 'closed',
            label: 'Đã đóng',
            value: s.closed ?? 0,
            tone: 'amber',
            icon: 'close',
            sub: total ? `${pct(s.closed ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.closed ?? 0),
            interactive: true,
            payload: { status: 'closed' },
        },
        {
            key: 'with_assignees',
            label: 'Có nhân sự',
            value: s.with_assignees ?? 0,
            tone: 'sky',
            icon: 'members',
            sub: total ? `${pct(s.with_assignees ?? 0)}% tổng` : 'Chưa gán nhân sự',
            progress: pct(s.with_assignees ?? 0),
            interactive: false,
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê phiếu đánh giá"
    heading="Tổng quan phiếu đánh giá"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
