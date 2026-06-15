<script setup>
import { ref } from 'vue';
import ProfileHero from './ProfileHero.vue';
import SkillMatrix from './SkillMatrix.vue';
import ProfileContactCard from './ProfileContactCard.vue';
import ProfileTeamsCard from './ProfileTeamsCard.vue';
import EditProfileModal from './EditProfileModal.vue';

defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
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

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
      <div class="space-y-5 lg:col-span-2">
        <SkillMatrix :skills="profile.skills" />
      </div>

      <aside class="space-y-5">
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
      @close="editing = false"
    />
  </div>
</template>
