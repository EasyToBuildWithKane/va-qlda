<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PerformanceFilterBar from '@/modules/performance/components/PerformanceFilterBar.vue';
import KpiCard from '@/modules/performance/components/KpiCard.vue';
import TrendChart from '@/modules/performance/components/TrendChart.vue';
import StatusDonut from '@/modules/performance/components/StatusDonut.vue';
import WorkloadBars from '@/modules/performance/components/WorkloadBars.vue';
import ProjectContributionChart from '@/modules/performance/components/ProjectContributionChart.vue';
import LeaderboardTable from '@/modules/performance/components/LeaderboardTable.vue';
import InsightsPanel from '@/modules/performance/components/InsightsPanel.vue';
import { usePerformanceExport } from '@/modules/performance/composables/usePerformanceExport.js';

const props = defineProps({
    filter: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    kpis: { type: Array, default: () => [] },
    headline: { type: Object, default: () => ({}) },
    statusDistribution: { type: Array, default: () => [] },
    workloadDistribution: { type: Array, default: () => [] },
    projectContribution: { type: Array, default: () => [] },
    trend: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
    insights: { type: Array, default: () => [] },
});

const { exportDashboard, printReport } = usePerformanceExport();

// Sparkline cho các KPI không có vòng tiến độ — dùng nhịp hoàn thành theo bucket.
const spark = computed(() => props.trend.map((b) => b.done));
const ringKeys = new Set(['completion', 'on_time', 'avg_score']);
function sparkFor(card) {
    return ringKeys.has(card.key) ? null : spark.value;
}

// Loading state (skeleton/overlay) khi đổi bộ lọc.
const processing = ref(false);
let offStart;
let offFinish;
onMounted(() => {
    offStart = router.on('start', () => { processing.value = true; });
    offFinish = router.on('finish', () => { processing.value = false; });
});
onUnmounted(() => { offStart?.(); offFinish?.(); });

function onExport() {
    exportDashboard({
        filter: props.filter,
        headline: props.headline,
        people: props.people,
    });
}
</script>

<template>
  <Head title="Bảng hiệu suất" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Bảng hiệu suất"
        :subtitle="`Phân tích hiệu suất & năng suất — ${filter.label || ''}`"
        icon="performance"
        icon-color="brand"
      />
    </template>

    <PerformanceFilterBar
      :filter="filter"
      :options="options"
      :processing="processing"
    >
      <template #actions>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
          @click="onExport"
        >
          <AppIcon
            name="export"
            :size="14"
          />
          Excel
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
          @click="printReport"
        >
          <AppIcon
            name="documents"
            :size="14"
          />
          In
        </button>
      </template>
    </PerformanceFilterBar>

    <div
      class="space-y-4 transition-opacity"
      :class="processing ? 'opacity-60' : 'opacity-100'"
    >
      <!-- KPI grid -->
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
        <KpiCard
          v-for="card in kpis"
          :key="card.key"
          :card="card"
          :spark="sparkFor(card)"
        />
      </div>

      <!-- Trend + status -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
          <h3 class="mb-4 flex items-center gap-2 font-display text-sm font-semibold text-slate-800">
            <AppIcon
              name="performance"
              :size="16"
              class="text-brand"
            />
            Xu hướng hiệu suất theo thời gian
          </h3>
          <TrendChart :trend="trend" />
        </section>

        <section class="card p-5">
          <h3 class="mb-4 flex items-center gap-2 font-display text-sm font-semibold text-slate-800">
            <AppIcon
              name="task"
              :size="16"
              class="text-brand"
            />
            Phân bố trạng thái
          </h3>
          <StatusDonut :distribution="statusDistribution" />
        </section>
      </div>

      <!-- Project contribution + workload -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section class="card p-5">
          <h3 class="mb-4 flex items-center gap-2 font-display text-sm font-semibold text-slate-800">
            <AppIcon
              name="projects"
              :size="16"
              class="text-brand"
            />
            Đóng góp theo dự án
          </h3>
          <ProjectContributionChart :contribution="projectContribution" />
        </section>

        <section class="card p-5">
          <h3 class="mb-4 flex items-center gap-2 font-display text-sm font-semibold text-slate-800">
            <AppIcon
              name="people"
              :size="16"
              class="text-brand"
            />
            Khối lượng & năng lực
          </h3>
          <WorkloadBars :people="workloadDistribution" />
        </section>
      </div>

      <!-- Leaderboard + insights -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <section class="card p-5 lg:col-span-2">
          <h3 class="mb-4 flex items-center gap-2 font-display text-sm font-semibold text-slate-800">
            <AppIcon
              name="leaderboard"
              :size="16"
              class="text-brand"
            />
            Bảng xếp hạng hiệu suất nhân sự
          </h3>
          <LeaderboardTable :people="people" />
        </section>

        <InsightsPanel :insights="insights" />
      </div>
    </div>
  </AppLayout>
</template>
