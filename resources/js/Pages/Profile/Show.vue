<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import ProfileView from '@/modules/profile/components/ProfileView.vue';

const props = defineProps({
    profile: { type: Object, default: null },
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
      v-if="props.profile"
      v-bind="props"
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
