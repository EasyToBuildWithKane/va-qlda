<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeHighlight: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    const h = props.activeHighlight;
    if (h === 'overdue' || h === 'dueToday' || h === 'atRisk' || h === 'clear' || h === 'inProgress') {
        return h;
    }
    if (!h || h === 'all') return 'members';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const members = s.members ?? 0;
    const open = s.open ?? 0;
    const pct = (n) => (open > 0 ? Math.round((n / open) * 100) : 0);

    return [
        {
            key: 'members',
            label: 'Thành viên',
            value: members,
            tone: 'brand',
            icon: 'people',
            sub: members ? 'Nhân sự bạn phụ trách' : 'Chưa có thành viên',
            interactive: true,
            payload: { highlight: 'all' },
        },
        {
            key: 'open',
            label: 'Tổng việc mở',
            value: open,
            tone: 'sky',
            icon: 'task',
            sub: members ? `Trung bình ${members ? Math.round(open / members) : 0} việc/người` : 'Bấm để xem tất cả',
            interactive: true,
            payload: { highlight: 'all' },
        },
        {
            key: 'overdue',
            label: 'Quá hạn',
            value: s.overdue ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: open ? `${pct(s.overdue ?? 0)}% tổng việc mở` : 'Bấm để lọc',
            progress: pct(s.overdue ?? 0),
            interactive: true,
            payload: { highlight: 'overdue' },
        },
        {
            key: 'dueToday',
            label: 'Đến hạn hôm nay',
            value: s.dueToday ?? 0,
            tone: 'amber',
            icon: 'calendar-clock',
            sub: open ? `${pct(s.dueToday ?? 0)}% tổng việc mở` : 'Bấm để lọc',
            progress: pct(s.dueToday ?? 0),
            interactive: true,
            payload: { highlight: 'dueToday' },
        },
        {
            key: 'inProgress',
            label: 'Đang làm',
            value: s.inProgress ?? 0,
            tone: 'violet',
            icon: 'sprint',
            sub: open ? `${pct(s.inProgress ?? 0)}% đang thực hiện` : 'Bấm để lọc',
            progress: pct(s.inProgress ?? 0),
            interactive: true,
            payload: { highlight: 'inProgress' },
        },
        {
            key: 'atRisk',
            label: 'Cần chú ý',
            value: s.atRisk ?? 0,
            tone: 'rose',
            icon: 'alert',
            sub: members ? `${s.atRisk ?? 0} thành viên có quá hạn hoặc hạn hôm nay` : 'Bấm để lọc',
            interactive: true,
            payload: { highlight: 'atRisk' },
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê nhóm phụ trách"
    eyebrow="Thống kê"
    heading="Tổng quan nhóm của tôi"
    hint="Thẻ viền nét đứt — bấm lọc nhanh danh sách thành viên"
    grid-class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.open ?? 0"
    @select="onSelect"
  />
</template>
