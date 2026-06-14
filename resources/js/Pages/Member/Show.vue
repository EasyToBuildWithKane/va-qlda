<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import ProfileView from '@/modules/profile/components/ProfileView.vue';

defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
    canViewPerformance: { type: Boolean, default: false },
    stats: { type: Object, default: null },
    projectExperience: { type: Array, default: null },
    activity: { type: Array, default: null },
});
</script>

<template>
  <Head :title="`Hồ sơ · ${profile.name}`" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="profile.name"
        :subtitle="profile.role_title || 'Hồ sơ thành viên'"
        icon="member-profiles"
        back-href="/members"
      />
    </template>

    <ProfileView
      :profile="profile"
      :editable="editable"
      :can-view-performance="canViewPerformance"
      :stats="stats"
      :project-experience="projectExperience"
      :activity="activity"
    />
  </AppLayout>
</template>
