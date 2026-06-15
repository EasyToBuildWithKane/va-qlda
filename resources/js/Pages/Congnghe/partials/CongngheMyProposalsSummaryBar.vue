<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (!props.activeStatus) return 'total';
    if (props.activeStatus === 'new') return 'new';
    if (props.activeStatus === 'triaged') return 'triaged';
    if (props.activeStatus === 'in_progress') return 'in_progress';
    if (props.activeStatus === 'done') return 'done';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng đề xuất',
            value: total,
            tone: 'brand',
            icon: 'rocket',
            sub: total ? 'Các đề xuất bạn đã gửi' : 'Chưa có đề xuất',
            interactive: true,
            payload: { status: '' },
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
            payload: { status: 'new' },
        },
        {
            key: 'triaged',
            label: 'Đã tiếp nhận',
            value: s.triaged ?? 0,
            tone: 'sky',
            icon: 'notifications',
            sub: total ? `${pct(s.triaged ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.triaged ?? 0),
            interactive: true,
            payload: { status: 'triaged' },
        },
        {
            key: 'in_progress',
            label: 'Đang xử lý',
            value: s.in_progress ?? 0,
            tone: 'amber',
            icon: 'sprint',
            sub: total ? `${pct(s.in_progress ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.in_progress ?? 0),
            interactive: true,
            payload: { status: 'in_progress' },
        },
        {
            key: 'done',
            label: 'Hoàn thành',
            value: s.done ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.done ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.done ?? 0),
            interactive: true,
            payload: { status: 'done' },
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    class="cn-portal-kpi"
    aria-label="Thống kê đề xuất của tôi"
    eyebrow="Thống kê"
    heading="Tổng quan đề xuất đã gửi"
    hint="Thẻ viền nét đứt — bấm để lọc nhanh"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
    shell-class="cn-portal-kpi__shell kpi-strip relative mb-5 overflow-x-clip rounded-2xl border border-white/10 bg-[#0a0c16]/75 px-4 py-4 shadow-[0_8px_40px_-12px_rgba(0,0,0,0.55)] backdrop-blur-xl sm:px-5 sm:py-5"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>

<style src="./congnghe-portal-kpi.css"></style>
