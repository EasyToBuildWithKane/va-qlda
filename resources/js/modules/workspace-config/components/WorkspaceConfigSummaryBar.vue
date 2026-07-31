<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
    activeReadiness: { type: String, default: '' },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeReadiness === 'ready') return 'ready';
    if (props.activeReadiness === 'partial') return 'partial';
    if (props.activeStatus === 'active') return 'active';
    if (props.activeStatus === 'draft') return 'draft';
    if (props.activeStatus === 'missing') return 'missing';
    if (!props.activeStatus && !props.activeReadiness) return 'total';
    return '';
});

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    const list = [
        {
            key: 'total',
            label: 'Tổng phòng ban',
            value: total,
            tone: 'brand',
            icon: 'department',
            sub: total ? 'Trong phạm vi của bạn' : 'Chưa có phòng ban',
            interactive: true,
            payload: { status: '', readiness: '' },
        },
        {
            key: 'active',
            label: 'Đang dùng',
            value: s.active ?? 0,
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.active ?? 0)}% đã kích hoạt` : 'Chưa kích hoạt',
            progress: pct(s.active ?? 0),
            interactive: true,
            payload: { status: 'active', readiness: '' },
        },
        {
            key: 'ready',
            label: 'Đã sẵn sàng',
            value: s.ready ?? 0,
            tone: 'sky',
            icon: 'award',
            sub: total
                ? `${s.with_criteria ?? 0} PB có tiêu chí`
                : 'Chưa có bộ tiêu chí',
            progress: pct(s.ready ?? 0),
            interactive: true,
            payload: { status: '', readiness: 'ready' },
        },
        {
            key: 'partial',
            label: 'Đang cấu hình',
            value: s.partial ?? 0,
            tone: 'violet',
            icon: 'documents',
            sub: total ? `${pct(s.partial ?? 0)}% đang thiết lập` : 'Không có bản nháp nội dung',
            progress: pct(s.partial ?? 0),
            interactive: true,
            payload: { status: '', readiness: 'partial' },
        },
        {
            key: 'criteria',
            label: 'Tiêu chí đánh giá',
            value: s.criteria_total ?? 0,
            tone: 'amber',
            icon: 'award',
            sub: s.criteria_general
                ? `${s.criteria_general} tiêu chí chung`
                : 'Chưa có tiêu chí',
            interactive: false,
        },
    ];

    if (props.canManage) {
        list.splice(2, 0, {
            key: 'missing',
            label: 'Chưa kích hoạt',
            value: s.missing ?? 0,
            tone: 'slate',
            icon: 'system-config',
            sub: total ? `${pct(s.missing ?? 0)}% chưa có profile` : 'Đã đủ profile',
            progress: pct(s.missing ?? 0),
            interactive: true,
            payload: { status: 'missing', readiness: '' },
        });
    }

    return list;
});

function onSelect(card) {
    if (card.payload) {
        emit('quick-filter', card.payload);
    }
}
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê cấu hình workspace theo phòng ban"
    heading="Tổng quan workspace phòng ban"
    hint="Thẻ viền nét đứt — bấm lọc nhanh theo trạng thái hoặc mức sẵn sàng"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.total ?? 0"
    :grid-class="canManage
      ? 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6'
      : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5'"
    @select="onSelect"
  />
</template>
