<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    statusFilter: { type: String, default: '' },
    projectFilter: { type: String, default: '' },
});

const emit = defineEmits(['filter-status', 'filter-project']);

const activeKey = computed(() => {
    if (props.projectFilter === 'assigned') return 'on_project';
    if (props.projectFilter === 'unassigned') return 'no_project';
    if (props.statusFilter === 'active') return 'active';
    if (props.statusFilter === 'inactive') return 'inactive';
    if (!props.statusFilter && !props.projectFilter) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const active = s.active ?? 0;
    const inactive = s.inactive ?? 0;
    const onProject = s.on_project ?? 0;
    const noProject = s.no_project ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng thành viên',
            value: total,
            tone: 'brand',
            icon: 'members',
            sub: total ? 'Danh bạ toàn hệ thống' : 'Chưa có nhân sự',
            interactive: true,
            payload: '',
        },
        {
            key: 'active',
            label: 'Đang hoạt động',
            value: active,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(active)}% tổng` : 'Bấm để lọc',
            progress: pct(active),
            interactive: true,
            payload: 'active',
        },
        {
            key: 'inactive',
            label: 'Ngừng hoạt động',
            value: inactive,
            tone: 'slate',
            icon: 'eye-off',
            sub: total ? `${pct(inactive)}% tổng` : 'Bấm để lọc',
            progress: pct(inactive),
            interactive: true,
            payload: 'inactive',
        },
        {
            key: 'on_project',
            label: 'Đang tham gia dự án',
            value: onProject,
            tone: 'sky',
            icon: 'projects',
            sub: total ? `${pct(onProject)}% có gán dự án` : 'Bấm để lọc',
            progress: pct(onProject),
            interactive: true,
            filterKind: 'project',
            payload: 'assigned',
        },
        {
            key: 'no_project',
            label: 'Chưa gán dự án',
            value: noProject,
            tone: 'amber',
            icon: 'projects',
            sub: 'Nhân sự đang hoạt động',
            interactive: true,
            filterKind: 'project',
            payload: 'unassigned',
        },
    ];
});

function onSelect(card) {
    if (card.filterKind === 'project') {
        emit('filter-project', card.payload);
        return;
    }
    if (card.payload !== undefined) {
        emit('filter-status', card.payload);
        emit('filter-project', '');
    }
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê danh bạ thành viên"
    heading="Tổng quan nhân sự"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh trạng thái"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
