<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import ProfileView from '@/modules/profile/components/ProfileView.vue';

defineProps({
    profile: { type: Object, default: null },
    editable: { type: Boolean, default: false },
    canViewPerformance: { type: Boolean, default: false },
    stats: { type: Object, default: null },
    projectExperience: { type: Array, default: null },
    activity: { type: Array, default: null },
});
</script>

<template>
  <Head title="Hồ sơ của tôi" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Hồ sơ của tôi"
        subtitle="Thông tin & năng lực cá nhân"
        icon="account"
      />
    </template>

    <ProfileView
      v-if="profile"
      :profile="profile"
      :editable="editable"
      :can-view-performance="canViewPerformance"
      :stats="stats"
      :project-experience="projectExperience"
      :activity="activity"
    />

    <div
      v-else
      class="mx-auto max-w-2xl"
    >
      <div class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-sm">
        <EmptyState
          icon="account"
          title="Tài khoản chưa liên kết hồ sơ nhân sự"
          description="Liên hệ quản trị viên để gắn tài khoản của bạn với một hồ sơ nhân viên."
        />
      </div>
    </div>
  </AppLayout>
</template>
