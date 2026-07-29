<script setup>
import { ref } from 'vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import ProfileHero from './ProfileHero.vue';
import ProfileOverviewSummaryBar from './ProfileOverviewSummaryBar.vue';
import ProfileHrInfo from './ProfileHrInfo.vue';
import ProfileWorkInfo from './ProfileWorkInfo.vue';
import ProfileContactCard from './ProfileContactCard.vue';
import SkillMatrix from './SkillMatrix.vue';
import SkillRadar from './SkillRadar.vue';
import ProfileInsights from './ProfileInsights.vue';
import ProfileAchievements from './ProfileAchievements.vue';

defineProps({
    profile: { type: Object, required: true },
});

const tab = ref('overview');
const tabs = [
    { key: 'overview', label: 'Tổng quan', icon: 'account' },
    { key: 'skills', label: 'Kỹ năng', icon: 'sparkles' },
    { key: 'achievements', label: 'Thành tích', icon: 'leaderboard' },
];
</script>

<template>
  <div class="w-full space-y-5">
    <ProfileHero :profile="profile" />

    <DatagridSegmentedControl
      v-model="tab"
      :items="tabs"
      aria-label="Mục hồ sơ"
      class="w-full"
    />

    <!-- Tổng quan -->
    <template v-if="tab === 'overview'">
      <ProfileOverviewSummaryBar
        :stats="profile.stats ?? {}"
        :active-tab="tab"
        @go-tab="tab = $event"
      />

      <div class="space-y-5">
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 lg:items-start">
          <ProfileHrInfo :hr-info="profile.hr_info ?? {}" />
          <div class="space-y-5">
            <ProfileContactCard :profile="profile" />
            <ProfileWorkInfo :profile="profile" />
          </div>
        </div>
      </div>
    </template>

    <!-- Năng lực & Kỹ năng (chỉ xem) -->
    <div
      v-else-if="tab === 'skills'"
      class="space-y-5"
    >
      <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 lg:items-start">
        <SkillMatrix :skills="profile.skills" />
        <SkillRadar :radar="profile.stats?.skill_radar || []" />
      </div>
      <ProfileInsights :insights="profile.stats?.insights || []" />
    </div>

    <!-- Thành tích -->
    <ProfileAchievements
      v-else
      :profile="profile"
    />
  </div>
</template>
