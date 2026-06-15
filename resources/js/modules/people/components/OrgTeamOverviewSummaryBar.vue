<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: 'all' },
    activeRole: { type: String, default: 'all' },
});

const emit = defineEmits(['quick-filter']);

const activeKey = computed(() => {
    if (props.activeRole === 'leaders') return 'leaders';
    if (props.activeStatus === 'active') return 'active';
    if (props.activeStatus === 'all' && props.activeRole === 'all') return 'people';
    return '';
});

const cards = computed(() => {
    const o = props.summary ?? {};
    const people = o.people_total ?? 0;
    const active = o.active_people ?? 0;
    const pct = (n) => (people > 0 ? Math.round((n / people) * 100) : 0);
    const skill = o.avg_skill_score;

    return [
        {
            key: 'people',
            label: 'Tổng nhân sự',
            value: people,
            tone: 'brand',
            icon: 'member-profiles',
            sub: `${active} đang hoạt động trên sơ đồ`,
            interactive: true,
            payload: { status: 'all', role: 'all', reset: true },
        },
        {
            key: 'teams',
            label: 'Phòng ban',
            value: o.teams_total ?? 0,
            tone: 'sky',
            icon: 'org-teams',
            sub: `${o.roots_total ?? 0} nhóm · ${o.subteams_total ?? 0} nhóm con`,
            interactive: false,
        },
        {
            key: 'leaders',
            label: 'Cấp quản lý',
            value: o.leaders_total ?? 0,
            tone: 'violet',
            icon: 'award',
            sub: 'Trưởng nhóm — bấm để lọc trên sơ đồ',
            interactive: true,
            payload: { status: 'all', role: 'leaders' },
        },
        {
            key: 'active',
            label: 'Đang hoạt động',
            value: active,
            tone: 'emerald',
            icon: 'check-circle',
            sub: people ? `${pct(active)}% nhân sự` : 'Bấm để lọc sơ đồ',
            progress: pct(active),
            interactive: true,
            payload: { status: 'active', role: 'all' },
        },
        {
            key: 'skill',
            label: 'Kỹ năng TB',
            value: skill != null && skill !== '' ? skill : '—',
            suffix: skill != null ? '/100' : '',
            tone: 'amber',
            icon: 'talent-score',
            sub: o.rated_people
                ? `Từ ${o.rated_people} hồ sơ có đánh giá`
                : 'Chưa có dữ liệu kỹ năng',
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
    aria-label="Thống kê sơ đồ tổ chức"
    heading="Tổng quan tổ chức"
    hint="Thẻ viền nét đứt — bấm để lọc nhanh trên sơ đồ"
    :cards="cards"
    :active-key="activeKey"
    :progress-denominator="summary.people_total ?? 0"
    @select="onSelect"
  />
</template>
