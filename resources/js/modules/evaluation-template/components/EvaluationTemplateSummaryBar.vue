<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeStatus === 'active') return 'active';
    if (props.activeStatus === 'inactive') return 'inactive';
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
            label: 'Tổng mẫu',
            value: total,
            tone: 'brand',
            icon: 'clipboard-list',
            sub: total ? 'Tất cả mẫu đánh giá' : 'Chưa có mẫu',
            interactive: true,
            payload: { status: '' },
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
            payload: { status: 'active' },
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
            payload: { status: 'inactive' },
        },
        {
            key: 'with_position',
            label: 'Có vị trí',
            value: s.with_position ?? 0,
            tone: 'sky',
            icon: 'members',
            sub: total ? `${pct(s.with_position ?? 0)}% tổng` : 'Chưa gán vị trí',
            progress: pct(s.with_position ?? 0),
            interactive: false,
        },
        {
            key: 'with_criteria',
            label: 'Có tiêu chí',
            value: s.with_criteria ?? 0,
            tone: 'violet',
            icon: 'award',
            sub: total ? `${pct(s.with_criteria ?? 0)}% tổng` : 'Chưa gắn tiêu chí',
            progress: pct(s.with_criteria ?? 0),
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
    aria-label="Thống kê mẫu đánh giá"
    heading="Tổng quan mẫu đánh giá"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
