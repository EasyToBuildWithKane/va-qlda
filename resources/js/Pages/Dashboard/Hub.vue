<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import HubWelcomeStrip from './partials/HubWelcomeStrip.vue';
import HubSummaryBar from './partials/HubSummaryBar.vue';
import HubModuleOverview from './partials/HubModuleOverview.vue';

defineProps({
    moduleGroups: { type: Array, default: () => [] },
    greeting: { type: Object, default: () => ({}) },
    summary: { type: Array, default: () => [] },
});
</script>

<template>
  <Head title="Tổng quan hệ thống" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Tổng quan hệ thống"
        subtitle="Chỉ số vận hành và truy cập module theo quyền"
        icon="overview"
        icon-color="brand"
      />
    </template>

    <HubWelcomeStrip :greeting="greeting" />

    <HubSummaryBar
      :summary="summary"
      class="mb-5"
    />

    <HubModuleOverview :module-groups="moduleGroups" />
  </AppLayout>
</template>
