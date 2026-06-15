<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import ProfileView from '@/modules/profile/components/ProfileView.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});
</script>

<template>
  <Head :title="`Hồ sơ · ${props.profile.name}`" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="props.profile.name"
        :subtitle="props.profile.role_title || 'Hồ sơ thành viên'"
        icon="member-profiles"
        back-href="/members"
      />
    </template>

    <ProfileView v-bind="props" />
  </AppLayout>
</template>
