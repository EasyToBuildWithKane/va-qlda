<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import HubWelcomeStrip from './partials/HubWelcomeStrip.vue';
import HubSystemSnapshot from './partials/HubSystemSnapshot.vue';
import HubModuleOverview from './partials/HubModuleOverview.vue';

defineProps({
    moduleGroups: { type: Array, default: () => [] },
    greeting: { type: Object, default: () => ({}) },
    systemSnapshot: { type: Object, default: () => ({ alerts: [], domains: [] }) },
});
</script>

<template>
  <Head title="Tổng quan hệ thống" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Tổng quan hệ thống"
        subtitle="Domain đa module — chi tiết công việc tại Dashboard công việc"
        icon="overview"
        icon-color="brand"
      />
    </template>

    <HubWelcomeStrip :greeting="greeting" />

    <HubSystemSnapshot :snapshot="systemSnapshot" />

    <HubModuleOverview :module-groups="moduleGroups" />
  </AppLayout>
</template>
