<script setup>
import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import ProfileHero from './ProfileHero.vue';
import ProfileWorkInfo from './ProfileWorkInfo.vue';
import ProfileContactCard from './ProfileContactCard.vue';
import ProfileTeamsCard from './ProfileTeamsCard.vue';
import SkillMatrix from './SkillMatrix.vue';
import SkillRadar from './SkillRadar.vue';
import ProfileInsights from './ProfileInsights.vue';
import ProfileAchievements from './ProfileAchievements.vue';
import EditProfileModal from './EditProfileModal.vue';
import SkillMatrixDrawer from './SkillMatrixDrawer.vue';

defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});

const tab = ref('overview');
const tabs = [
    { key: 'overview', label: 'Tổng quan', icon: 'account' },
    { key: 'skills', label: 'Năng lực & Kỹ năng', icon: 'sparkles' },
    { key: 'achievements', label: 'Thành tích', icon: 'leaderboard' },
];

const editingIdentity = ref(false);
const editingSkills = ref(false);
</script>

<template>
  <div class="mx-auto max-w-6xl space-y-5">
    <ProfileHero
      :profile="profile"
      :editable="editable"
      @edit="editingIdentity = true"
    />

    <DatagridSegmentedControl
      v-model="tab"
      :items="tabs"
      aria-label="Mục hồ sơ"
    />

    <!-- Tổng quan -->
    <div
      v-if="tab === 'overview'"
      class="grid grid-cols-1 gap-5 lg:grid-cols-3"
    >
      <div class="space-y-5 lg:col-span-2">
        <ProfileWorkInfo :profile="profile" />
        <ProfileContactCard :profile="profile" />
      </div>
      <aside class="space-y-5">
        <ProfileTeamsCard
          :teams="profile.teams"
          :manager="profile.manager"
        />
      </aside>
    </div>

    <!-- Năng lực & Kỹ năng -->
    <div
      v-else-if="tab === 'skills'"
      class="space-y-5"
    >
      <div
        v-if="editable"
        class="flex justify-end"
      >
        <button
          type="button"
          class="btn-primary flex items-center gap-1.5 text-[13px]"
          @click="editingSkills = true"
        >
          <AppIcon
            name="sparkles"
            :size="15"
          />
          Quản lý ma trận kỹ năng
        </button>
      </div>

      <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2">
          <SkillMatrix :skills="profile.skills" />
        </div>
        <aside class="space-y-5">
          <SkillRadar :radar="profile.stats?.skill_radar || []" />
          <ProfileInsights :insights="profile.stats?.insights || []" />
        </aside>
      </div>
    </div>

    <!-- Thành tích -->
    <ProfileAchievements
      v-else
      :profile="profile"
    />

    <EditProfileModal
      v-if="editable"
      :show="editingIdentity"
      :profile="profile"
      @close="editingIdentity = false"
    />
    <SkillMatrixDrawer
      v-if="editable"
      :show="editingSkills"
      :profile="profile"
      @close="editingSkills = false"
    />
  </div>
</template>
