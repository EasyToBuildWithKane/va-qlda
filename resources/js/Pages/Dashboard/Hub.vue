<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
import HubWelcomeStrip from './partials/HubWelcomeStrip.vue';
import HubAlertsStrip from './partials/HubAlertsStrip.vue';
import HubActivityTrendChart from './partials/HubActivityTrendChart.vue';
import HubCompliancePanel from './partials/HubCompliancePanel.vue';
import HubModuleOverview from './partials/HubModuleOverview.vue';

defineProps({
    greeting: { type: Object, default: () => ({}) },
    kpiCards: { type: Array, default: () => [] },
    activityTrend: { type: Array, default: () => [] },
    compliance: { type: Object, default: () => ({}) },
    alerts: { type: Array, default: () => [] },
    moduleGroups: { type: Array, default: () => [] },
});
</script>

<template>
  <Head title="Tổng quan hệ thống" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Tổng quan hệ thống"
        subtitle="Chỉ số chính, xu hướng hoạt động và lối vào nhanh các module"
        icon="overview"
        icon-color="brand"
      />
    </template>

    <HubWelcomeStrip :greeting="greeting" />

    <KpiSummaryStrip
      :cards="kpiCards"
      eyebrow="Chỉ số chính"
      heading="Sức khỏe hệ thống hôm nay"
      aria-label="Chỉ số tổng quan hệ thống"
      grid-class="grid-cols-2 sm:grid-cols-3 xl:grid-cols-6"
    />

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

    <HubAlertsStrip
      :alerts="alerts"
      class="mb-5"
    />

    <HubModuleOverview :module-groups="moduleGroups" />
  </AppLayout>
</template>
