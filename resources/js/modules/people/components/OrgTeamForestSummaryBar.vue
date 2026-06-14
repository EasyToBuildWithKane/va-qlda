<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';

const props = defineProps({
    stats: { type: Object, required: true },
});

const cards = computed(() => {
    const s = props.stats;
    const roots = s.rootCount ?? 0;
    const teams = s.teamCount ?? 0;
    const people = s.peopleCount ?? 0;
    return [
        {
            key: 'roots',
            label: 'Ban / Khối',
            value: roots,
            tone: 'brand',
            icon: 'org-teams',
            sub: roots === 1 ? '1 team gốc độc lập' : `${roots} team gốc — mỗi thẻ bên dưới là một Ban/Khối`,
            interactive: false,
        },
        {
            key: 'teams',
            label: 'Nhóm trên sơ đồ',
            value: teams,
            tone: 'sky',
            icon: 'members',
            sub: roots ? `Trung bình ${teams && roots ? Math.round(teams / roots) : 0} nhóm / Ban` : 'Gồm nhóm con mọi cấp',
            interactive: false,
        },
        {
            key: 'people',
            label: 'Thành viên',
            value: people,
            tone: 'emerald',
            icon: 'member-profiles',
            sub: people ? 'Người gắn trên sơ đồ (trưởng + thành viên nhóm)' : 'Chưa gán nhân sự',
            interactive: false,
        },
    ];
});
</script>

<template>
  <KpiSummaryStrip
    aria-label="Thống kê sơ đồ team"
    heading="Quy mô tổ chức"
    hint="Số liệu toàn hệ thống — bấm thẻ Ban/Khối bên dưới để mở sơ đồ chi tiết"
    grid-class="grid-cols-1 gap-3 sm:grid-cols-3"
    :cards="cards"
    :progress-denominator="0"
  />
</template>
