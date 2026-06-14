<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeScope: { type: String, default: '' },
    activeStatus: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeScope === 'open') return 'open';
    if (props.activeStatus === 'new') return 'new';
    if (props.activeStatus === 'resolved') return 'resolved';
    if (!props.activeScope && !props.activeStatus) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const open = s.open ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng phản hồi',
            value: total,
            tone: 'brand',
            icon: 'feedback',
            sub: total ? 'Toàn hệ thống' : 'Chưa có phản hồi',
            interactive: true,
            payload: { scope: '', status: '' },
        },
        {
            key: 'open',
            label: 'Đang xử lý',
            value: open,
            tone: 'sky',
            icon: 'sprint',
            sub: total ? `${pct(open)}% chưa kết thúc` : 'Bấm để lọc',
            progress: pct(open),
            interactive: true,
            payload: { scope: 'open', status: '' },
        },
        {
            key: 'new',
            label: 'Mới',
            value: s.new ?? 0,
            tone: 'violet',
            icon: 'add',
            sub: total ? `${pct(s.new ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.new ?? 0),
            interactive: true,
            payload: { scope: '', status: 'new' },
        },
        {
            key: 'resolved',
            label: 'Đã xử lý',
            value: s.resolved ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.resolved ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.resolved ?? 0),
            interactive: true,
            payload: { scope: '', status: 'resolved' },
        },
        {
            key: 'rating',
            label: 'Đánh giá TB',
            value: s.avg_rating != null && s.avg_rating !== '' ? s.avg_rating : '—',
            suffix: s.avg_rating ? '★' : '',
            suffixClass: 'text-amber-500',
            tone: 'amber',
            icon: 'star',
            sub: 'Thang 1–5 sao từ người gửi',
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
    aria-label="Thống kê phản hồi"
    heading="Tổng quan phản hồi hệ thống"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
