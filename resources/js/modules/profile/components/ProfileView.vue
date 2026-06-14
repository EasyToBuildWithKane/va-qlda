<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProfileHero from './ProfileHero.vue';
import ProfileStatStrip from './ProfileStatStrip.vue';
import TalentScoreCard from './TalentScoreCard.vue';
import SkillMatrix from './SkillMatrix.vue';
import SkillGapCard from './SkillGapCard.vue';
import CareerRoadmap from './CareerRoadmap.vue';
import CertificationsGrid from './CertificationsGrid.vue';
import LearningCenter from './LearningCenter.vue';
import KpiDashboard from './KpiDashboard.vue';
import ProjectExperience from './ProjectExperience.vue';
import Feedback360 from './Feedback360.vue';
import SuccessionCard from './SuccessionCard.vue';
import ActivityTimeline from './ActivityTimeline.vue';
import ProfileContactCard from './ProfileContactCard.vue';
import ProfileTeamsCard from './ProfileTeamsCard.vue';
import EditProfileModal from './EditProfileModal.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
    canViewPerformance: { type: Boolean, default: false },
    canViewSuccession: { type: Boolean, default: false },
    stats: { type: Object, default: null },
    projectExperience: { type: Array, default: null },
    activity: { type: Array, default: null },
    talentScore: { type: Object, default: null },
    skillGap: { type: Object, default: null },
    careerRoadmap: { type: Array, default: () => [] },
    kpis: { type: Array, default: null },
    certifications: { type: Array, default: () => [] },
    learning: { type: Array, default: () => [] },
    feedback360: { type: Object, default: null },
    succession: { type: Object, default: null },
});

const tabs = computed(() => [
    { key: 'overview', label: 'Tổng quan', icon: 'account', visible: true },
    { key: 'growth', label: 'Kỹ năng & Phát triển', icon: 'sparkles', visible: true },
    { key: 'performance', label: 'Hiệu suất', icon: 'performance', visible: props.canViewPerformance },
    { key: 'succession', label: 'Kế nhiệm', icon: 'leaderboard', visible: props.canViewSuccession && !!props.succession },
].filter((t) => t.visible));

const active = ref('overview');
const editing = ref(false);
</script>

<template>
  <div class="mx-auto max-w-6xl space-y-5">
    <ProfileHero
      :profile="profile"
      :editable="editable"
      @edit="editing = true"
    />

    <ProfileStatStrip
      v-if="canViewPerformance && stats"
      :stats="stats"
    />

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
      <!-- Main column -->
      <div class="space-y-5 lg:col-span-2">
        <!-- Tab bar -->
        <div class="flex flex-wrap items-center gap-1 rounded-xl border border-slate-200/70 bg-white p-1 shadow-sm">
          <button
            v-for="t in tabs"
            :key="t.key"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-[13px] font-medium transition-colors"
            :class="active === t.key ? 'bg-brand/10 text-brand' : 'text-slate-500 hover:bg-slate-50'"
            @click="active = t.key"
          >
            <AppIcon
              :name="t.icon"
              :size="14"
            />
            {{ t.label }}
          </button>
        </div>

        <!-- Tổng quan -->
        <template v-if="active === 'overview'">
          <SkillMatrix :skills="profile.skills" />
          <ActivityTimeline
            v-if="canViewPerformance"
            :items="activity || []"
          />
          <div
            v-else
            class="flex items-center gap-3 rounded-2xl border border-slate-200/70 bg-white p-5 text-[13px] text-slate-500 shadow-sm"
          >
            <div class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-400">
              <AppIcon
                name="eye-off"
                :size="16"
              />
            </div>
            Dữ liệu hiệu suất, KPI và đánh giá 360° chỉ hiển thị cho quản lý hoặc chính chủ hồ sơ.
          </div>
        </template>

        <!-- Kỹ năng & Phát triển -->
        <template v-else-if="active === 'growth'">
          <SkillGapCard
            v-if="skillGap"
            :gap="skillGap"
          />
          <CareerRoadmap :levels="careerRoadmap" />
          <CertificationsGrid :items="certifications" />
          <LearningCenter :items="learning" />
        </template>

        <!-- Hiệu suất -->
        <template v-else-if="active === 'performance'">
          <KpiDashboard :groups="kpis || []" />
          <ProjectExperience :items="projectExperience || []" />
          <Feedback360
            v-if="feedback360"
            :data="feedback360"
          />
        </template>

        <!-- Kế nhiệm -->
        <template v-else-if="active === 'succession'">
          <SuccessionCard :data="succession" />
        </template>
      </div>

      <!-- Sidebar -->
      <aside class="space-y-5">
        <TalentScoreCard
          v-if="canViewPerformance && talentScore"
          :score="talentScore"
        />
        <ProfileContactCard :profile="profile" />
        <ProfileTeamsCard
          :teams="profile.teams"
          :manager="profile.manager"
        />
      </aside>
    </div>

    <EditProfileModal
      v-if="editable"
      :show="editing"
      :profile="profile"
      :certifications="certifications"
      @close="editing = false"
    />
  </div>
</template>
