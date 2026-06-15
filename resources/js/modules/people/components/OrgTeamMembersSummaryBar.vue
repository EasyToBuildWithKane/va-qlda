<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: 'all' },
});

const emit = defineEmits(['filter-status']);

const activeKey = computed(() => {
    if (props.activeStatus === 'active') return 'active';
    if (props.activeStatus === 'inactive') return 'inactive';
    if (props.activeStatus === 'all' || !props.activeStatus) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const active = s.active ?? 0;
    const leaders = s.leaders ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Trong sơ đồ',
            value: total,
            tone: 'brand',
            icon: 'members',
            sub: 'Thành viên gán trong nhóm — không tính quản lý',
            interactive: true,
            payload: 'all',
        },
        {
            key: 'leaders',
            label: 'Quản lý',
            value: leaders,
            tone: 'amber',
            icon: 'member-profiles',
            sub: total ? `${pct(leaders)}% tổng — đang là quản lý ít nhất một nhóm` : 'Chưa có quản lý',
            interactive: false,
        },
        {
            key: 'active',
            label: 'Đang hoạt động',
            value: active,
            tone: 'emerald',
            icon: 'check-circle',
            sub: total ? `${pct(active)}% tổng` : 'Bấm để lọc danh sách',
            progress: pct(active),
            interactive: true,
            payload: 'active',
        },
        {
            key: 'inactive',
            label: 'Ngừng hoạt động',
            value: s.inactive ?? Math.max(0, total - active),
            tone: 'slate',
            icon: 'archive',
            sub: 'Tài khoản ngừng — bấm để lọc',
            interactive: true,
            payload: 'inactive',
        },
    ];
});

function onSelect(card) {
    if (card.payload) emit('filter-status', card.payload);
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê thành viên phòng"
    heading="Thành viên trên sơ đồ"
    hint="Thẻ viền nét đứt — bấm để lọc trạng thái trong bảng"
    grid-class="grid-cols-2 gap-3 sm:grid-cols-2 lg:grid-cols-4"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
