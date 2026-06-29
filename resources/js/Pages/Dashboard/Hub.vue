<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import HubDashboardSummaryBar from './partials/HubDashboardSummaryBar.vue';
import HubWelcomeStrip from './partials/HubWelcomeStrip.vue';
import HubActivityTrendChart from './partials/HubActivityTrendChart.vue';
import HubCompliancePanel from './partials/HubCompliancePanel.vue';
import HubModuleOverview from './partials/HubModuleOverview.vue';

defineProps({
    greeting: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
    activityTrend: { type: Array, default: () => [] },
    compliance: { type: Object, default: () => ({}) },
    moduleGroups: { type: Array, default: () => [] },
});
</script>

<template>
  <Head title="Tổng quan hệ thống" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Tổng quan hệ thống"
        subtitle="Xu hướng hoạt động, tuân thủ báo cáo và lối vào nhanh các module"
        icon="overview"
        icon-color="brand"
      />
    </template>

    <HubWelcomeStrip :greeting="greeting" />

    <HubDashboardSummaryBar :summary="summary" />

    <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-5">
      <HubActivityTrendChart
        :trend="activityTrend"
        class="lg:col-span-3"
      />
      <HubCompliancePanel
        :compliance="compliance"
        class="lg:col-span-2"
      />
    </div>

    <HubModuleOverview :module-groups="moduleGroups" />
  </AppLayout>
</template>
