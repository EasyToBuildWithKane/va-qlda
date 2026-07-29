<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
    activeTemplateType: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeStatus === 'effective') return 'effective';
    if (props.activeStatus === 'active') return 'active';
    if (props.activeTemplateType === 'point_system') return 'point_system';
    if (props.activeTemplateType === 'scorecard') return 'scorecard';
    if (!props.activeStatus && !props.activeTemplateType) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng cấu hình',
            value: total,
            tone: 'brand',
            icon: 'award',
            sub: total ? 'Tất cả phòng ban' : 'Chưa có cấu hình',
            interactive: true,
            payload: { status: '', template_type: '' },
        },
        {
            key: 'effective',
            label: 'Đang hiệu lực',
            value: s.effective ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.effective ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.effective ?? 0),
            interactive: true,
            payload: { status: 'effective', template_type: '' },
        },
        {
            key: 'active',
            label: 'Đang bật',
            value: s.active ?? 0,
            tone: 'sky',
            icon: 'sprint',
            sub: total ? `${pct(s.active ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.active ?? 0),
            interactive: true,
            payload: { status: 'active', template_type: '' },
        },
        {
            key: 'point_system',
            label: 'Điểm cộng/trừ',
            value: s.point_system ?? 0,
            tone: 'violet',
            icon: 'performance',
            sub: 'Mẫu HCNS',
            progress: pct(s.point_system ?? 0),
            interactive: true,
            payload: { status: '', template_type: 'point_system' },
        },
        {
            key: 'scorecard',
            label: 'Phiếu tiêu chí',
            value: s.scorecard ?? 0,
            tone: 'amber',
            icon: 'documents',
            sub: 'Mẫu CNTT',
            progress: pct(s.scorecard ?? 0),
            interactive: true,
            payload: { status: '', template_type: 'scorecard' },
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê cấu hình đánh giá"
    heading="Tổng quan cấu hình đánh giá"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
