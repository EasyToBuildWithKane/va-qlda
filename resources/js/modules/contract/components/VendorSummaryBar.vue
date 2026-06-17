<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';
import { EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    summary: { type: Object, required: true },
    activeScope: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeScope === 'with_contracts') return 'with_contracts';
    if (props.activeScope === 'low_score') return 'low_score';
    if (!props.activeScope) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng nhà cung cấp',
            value: total,
            tone: 'brand',
            icon: 'vendor',
            sub: total ? 'Toàn danh sách' : 'Chưa có NCC',
            interactive: true,
            payload: { scope: '' },
        },
        {
            key: 'with_contracts',
            label: 'Đang hợp tác',
            value: s.with_contracts ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.with_contracts ?? 0)}% có hợp đồng` : 'Bấm để lọc',
            progress: pct(s.with_contracts ?? 0),
            interactive: true,
            payload: { scope: 'with_contracts' },
        },
        {
            key: 'contracts',
            label: 'Tổng hợp đồng',
            value: s.contracts ?? 0,
            tone: 'sky',
            icon: 'documents',
            sub: 'Trên toàn bộ NCC',
            interactive: false,
        },
        {
            key: 'annual_cost',
            label: 'Chi phí năm',
            value: formatMoneyShort(s.annual_cost ?? 0),
            tone: 'violet',
            icon: 'money',
            sub: 'Tổng chi phí theo NCC',
            interactive: false,
        },
        {
            key: 'avg_score',
            label: 'Điểm TB đánh giá',
            value: s.avg_score != null ? `${s.avg_score}/10` : EMPTY_LABELS.grade,
            tone: 'amber',
            icon: 'performance',
            sub: total ? `${s.reviewed ?? 0} NCC đã đánh giá` : EMPTY_LABELS.generic,
            interactive: false,
        },
        {
            key: 'low_score',
            label: 'NCC chấm < 7',
            value: s.low_score ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: total ? `${pct(s.low_score ?? 0)}% đã đánh giá` : 'Bấm để lọc',
            progress: pct(s.low_score ?? 0),
            interactive: true,
            payload: { scope: 'low_score' },
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê nhà cung cấp"
    heading="Tổng quan nhà cung cấp"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    grid-class="grid-cols-2 sm:grid-cols-3 lg:grid-cols-6"
    @select="onSelect"
  />
</template>
