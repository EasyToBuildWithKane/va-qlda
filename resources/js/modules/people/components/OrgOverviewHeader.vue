<script setup>
import { computed } from 'vue';
import OrgStatCard from './OrgStatCard.vue';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
});

const cards = computed(() => {
    const o = props.overview ?? {};
    return [
        {
            key: 'people',
            label: 'Tổng nhân sự',
            value: o.people_total ?? 0,
            icon: 'member-profiles',
            tone: 'brand',
            sub: `${o.active_people ?? 0} đang hoạt động`,
        },
        {
            key: 'teams',
            label: 'Phòng ban',
            value: o.teams_total ?? 0,
            icon: 'org-teams',
            tone: 'sky',
            sub: `${o.roots_total ?? 0} Ban/Khối · ${o.subteams_total ?? 0} nhóm`,
        },
        {
            key: 'leaders',
            label: 'Cấp quản lý',
            value: o.leaders_total ?? 0,
            icon: 'award',
            tone: 'violet',
            sub: 'Trưởng Ban / Nhóm',
        },
        {
            key: 'active',
            label: 'Tỷ lệ hoạt động',
            value: o.active_ratio ?? 0,
            suffix: '%',
            icon: 'performance',
            tone: 'emerald',
            progress: o.active_ratio ?? 0,
            sub: `${o.active_people ?? 0}/${o.people_total ?? 0} nhân sự`,
        },
        {
            key: 'skill',
            label: 'Kỹ năng trung bình',
            value: o.avg_skill_score ?? null,
            suffix: o.avg_skill_score != null ? '/100' : '',
            icon: 'talent-score',
            tone: 'amber',
            progress: o.avg_skill_score ?? null,
            sub: o.rated_people
                ? `Từ ${o.rated_people} hồ sơ có đánh giá kỹ năng`
                : 'Chưa có dữ liệu kỹ năng',
        },
    ];
});
</script>

<template>
  <section
    class="rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white px-4 py-4 shadow-sm sm:px-5"
    aria-label="Tổng quan tổ chức"
  >
    <header class="mb-3">
      <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
        Trung tâm điều hành
      </p>
      <h2 class="font-display text-sm font-semibold text-slate-800">
        Tổng quan tổ chức
      </h2>
    </header>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
      <OrgStatCard
        v-for="(card, i) in cards"
        :key="card.key"
        :index="i"
        v-bind="card"
      />
    </div>
  </section>
</template>
