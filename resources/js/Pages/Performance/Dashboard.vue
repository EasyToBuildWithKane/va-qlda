<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import PerformanceFilterBar from '@/modules/performance/components/PerformanceFilterBar.vue';
import PerformanceDashboardSummaryBar from '@/modules/performance/components/PerformanceDashboardSummaryBar.vue';
import TrendChart from '@/modules/performance/components/TrendChart.vue';
import StatusDonut from '@/modules/performance/components/StatusDonut.vue';
import WorkloadBars from '@/modules/performance/components/WorkloadBars.vue';
import ProjectContributionChart from '@/modules/performance/components/ProjectContributionChart.vue';
import LeaderboardTable from '@/modules/performance/components/LeaderboardTable.vue';
import { usePerformanceExport } from '@/modules/performance/composables/usePerformanceExport.js';

const props = defineProps({
    filter: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    headline: { type: Object, default: () => ({}) },
    statusDistribution: { type: Array, default: () => [] },
    workloadDistribution: { type: Array, default: () => [] },
    projectContribution: { type: Array, default: () => [] },
    trend: { type: Array, default: () => [] },
    people: { type: Array, default: () => [] },
});

const { exportDashboard, printReport } = usePerformanceExport();

const leaderboardQuery = ref('');

const filteredPeople = computed(() => {
    const q = leaderboardQuery.value.trim().toLowerCase();
    if (!q) return props.people;
    return props.people.filter((p) =>
        (p.name ?? '').toLowerCase().includes(q)
        || (p.role ?? '').toLowerCase().includes(q),
    );
});

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
        :badge="summary.people_count || null"
      />
    </template>

    <PerformanceDashboardSummaryBar :summary="summary" />

    <PerformanceFilterBar
      :filter="filter"
      :options="options"
      :processing="processing"
      @export-excel="onExport"
    >
      <template #actions>
        <button
          type="button"
          class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50"
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

      <section class="card overflow-visible">
        <div class="border-b border-slate-100 px-5 py-4">
          <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
            <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
              <DatagridToolbarSearch
                v-model="leaderboardQuery"
                input-id="performance-leaderboard-search"
                placeholder="Tìm thành viên, vai trò…"
                stretch
                inline-actions
                hide-label
                input-height="h-10"
              />
            </div>
          </div>
        </div>
        <div class="p-5 pt-4">
          <h3 class="mb-4 flex items-center gap-2 font-display text-sm font-semibold text-slate-800">
            <AppIcon
              name="leaderboard"
              :size="16"
              class="text-brand"
            />
            Bảng xếp hạng hiệu suất nhân sự
          </h3>
          <LeaderboardTable :people="filteredPeople" />
        </div>
      </section>
    </div>
  </AppLayout>
</template>
