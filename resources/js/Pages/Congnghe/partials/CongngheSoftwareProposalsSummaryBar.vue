<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
    activeEmailPending: { type: Boolean, default: false },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeEmailPending) return 'email_pending';
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
            icon: 'template',
            sub: total ? 'Toàn bộ gửi tới Phòng Công nghệ' : 'Chưa có đề xuất',
            interactive: true,
            payload: { status: '', email_pending: false },
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
            payload: { status: 'new', email_pending: false },
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
            payload: { status: 'triaged', email_pending: false },
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
            payload: { status: 'in_progress', email_pending: false },
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
            payload: { status: 'done', email_pending: false },
        },
        {
            key: 'email_pending',
            label: 'Chưa gửi email',
            value: s.email_pending ?? 0,
            tone: 'rose',
            icon: 'mail',
            sub: 'Cần gửi thông báo cho người đề xuất',
            interactive: true,
            payload: { status: '', email_pending: true },
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('quick-filter', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê đề xuất phần mềm"
    heading="Tổng quan đề xuất phần mềm"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh danh sách"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
