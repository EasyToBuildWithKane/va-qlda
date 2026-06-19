<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const canManageStatus = computed(() => Boolean(props.summary.can_manage_status));

const activeKey = computed(() => {
    if (!props.activeStatus) return 'total';
    if (props.activeStatus === 'published') return 'published';
    if (props.activeStatus === 'draft') return 'draft';
    if (props.activeStatus === 'archived') return 'archived';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    const draftInteractive = canManageStatus.value;
    const archivedInteractive = canManageStatus.value;

    return [
        {
            key: 'total',
            label: 'Tổng bài viết',
            value: total,
            tone: 'brand',
            icon: 'knowledge',
            sub: total ? 'Trong phạm vi bạn xem được' : 'Chưa có bài',
            interactive: true,
            payload: { status: '' },
        },
        {
            key: 'published',
            label: 'Đã xuất bản',
            value: s.published ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.published ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.published ?? 0),
            interactive: canManageStatus.value,
            payload: { status: 'published' },
        },
        {
            key: 'draft',
            label: 'Nháp',
            value: s.draft ?? 0,
            tone: 'violet',
            icon: 'edit',
            sub: draftInteractive ? 'Chỉ quản trị / lead' : 'Không hiển thị với bạn',
            progress: canManageStatus.value ? pct(s.draft ?? 0) : null,
            interactive: draftInteractive,
            payload: { status: 'draft' },
        },
        {
            key: 'archived',
            label: 'Lưu trữ',
            value: s.archived ?? 0,
            tone: 'slate',
            icon: 'archive',
            sub: archivedInteractive ? 'Bài không còn trên blog' : '—',
            progress: canManageStatus.value ? pct(s.archived ?? 0) : null,
            interactive: archivedInteractive,
            payload: { status: 'archived' },
        },
        {
            key: 'views',
            label: 'Lượt xem',
            value: s.total_views ?? 0,
            tone: 'sky',
            icon: 'eye',
            sub: 'Tổng trên các bài đang xem',
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
    aria-label="Thống kê cơ sở tri thức"
    heading="Tổng quan thư viện tri thức"
    hint="Thẻ có viền nét đứt — bấm để lọc nhanh theo trạng thái"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    @select="onSelect"
  />
</template>
