<script setup>
import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProfileHero from './ProfileHero.vue';
import ProfileStatStrip from './ProfileStatStrip.vue';
import SkillMatrix from './SkillMatrix.vue';
import ProjectExperience from './ProjectExperience.vue';
import ActivityTimeline from './ActivityTimeline.vue';
import ProfileContactCard from './ProfileContactCard.vue';
import ProfileTeamsCard from './ProfileTeamsCard.vue';
import AdvancedModulesTeaser from './AdvancedModulesTeaser.vue';
import EditProfileModal from './EditProfileModal.vue';

defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
    canViewPerformance: { type: Boolean, default: false },
    stats: { type: Object, default: null },
    projectExperience: { type: Array, default: null },
    activity: { type: Array, default: null },
});

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
        <SkillMatrix :skills="profile.skills" />

        <ProjectExperience
          v-if="canViewPerformance"
          :items="projectExperience || []"
        />

        <!-- Locked notice for non-managers viewing someone else -->
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
          Dữ liệu hiệu suất, lịch sử dự án và nhật ký hoạt động chỉ hiển thị cho quản lý hoặc chính chủ hồ sơ.
        </div>

        <ActivityTimeline
          v-if="canViewPerformance"
          :items="activity || []"
        />
      </div>

      <!-- Sidebar -->
      <aside class="space-y-5">
        <ProfileContactCard :profile="profile" />
        <ProfileTeamsCard
          :teams="profile.teams"
          :manager="profile.manager"
        />
        <AdvancedModulesTeaser />
      </aside>
    </div>

    <EditProfileModal
      v-if="editable"
      :show="editing"
      :profile="profile"
      @close="editing = false"
    />
  </div>
</template>
