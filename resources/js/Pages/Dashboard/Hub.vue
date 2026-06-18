<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import HubSummaryBar from './partials/HubSummaryBar.vue';
import HubActivityTrendChart from './partials/HubActivityTrendChart.vue';
import HubStatusDonut from './partials/HubStatusDonut.vue';
import HubSeverityChart from './partials/HubSeverityChart.vue';
import HubCompliancePanel from './partials/HubCompliancePanel.vue';

const props = defineProps({
    moduleGroups: { type: Array, default: () => [] },
    greeting:     { type: Object, default: () => ({}) },
    summary:      { type: Array, default: () => [] },
    charts:       { type: Object, default: () => ({}) },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? {});
const roleLabelMap = {
    super_admin: 'Siêu quản trị',
    admin: 'Quản trị viên',
    lead: 'Quản lý',
    member: 'Thành viên',
    viewer: 'Quan sát',
};
const roleLabel = computed(() => roleLabelMap[user.value.role] ?? user.value.role ?? '');

// ── Tone helpers ────────────────────────────────────────────────────
const toneIcon = {
    brand:   'text-brand bg-brand/10 ring-brand/20',
    emerald: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
    amber:   'text-amber-700 bg-amber-50 ring-amber-200/80',
    sky:     'text-sky-700 bg-sky-50 ring-sky-200/80',
    violet:  'text-violet-700 bg-violet-50 ring-violet-200/80',
    rose:    'text-rose-700 bg-rose-50 ring-rose-200/80',
    slate:   'text-slate-600 bg-slate-100 ring-slate-200/80',
};

const toneStat = {
    brand:   'text-brand',
    emerald: 'text-emerald-700',
    amber:   'text-amber-700',
    sky:     'text-sky-700',
    violet:  'text-violet-700',
    rose:    'text-rose-700',
    slate:   'text-slate-600',
};

const toneGroupBorder = {
    brand:   'border-brand/20 bg-brand/5',
    emerald: 'border-emerald-200/60 bg-emerald-50/40',
    amber:   'border-amber-200/60 bg-amber-50/40',
    sky:     'border-sky-200/60 bg-sky-50/40',
    violet:  'border-violet-200/60 bg-violet-50/40',
    rose:    'border-rose-200/60 bg-rose-50/40',
    slate:   'border-slate-200/60 bg-slate-50/40',
};

const roleRingColor = {
    super_admin: 'bg-rose-100 text-rose-700 ring-rose-300/60',
    admin: 'bg-brand/10 text-brand ring-brand/20',
    lead: 'bg-violet-100 text-violet-700 ring-violet-300/60',
    member: 'bg-sky-100 text-sky-700 ring-sky-300/60',
    viewer: 'bg-slate-100 text-slate-600 ring-slate-300/60',
};
const roleRing = computed(() => roleRingColor[user.value.role] ?? 'bg-slate-100 text-slate-600 ring-slate-300/60');

const hasSeverity = computed(() => Array.isArray(props.charts.blockerSeverity));
const hasCompliance = computed(() => !!props.charts.reportCompliance);
</script>

<template>
  <Head title="Tổng quan hệ thống" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Tổng quan hệ thống"
        subtitle="Trung tâm điều hành — chỉ số, xu hướng & truy cập nhanh"
        icon="overview"
        icon-color="brand"
      />
    </template>

    <!-- Welcome strip -->
    <div class="mb-5 overflow-hidden rounded-card border border-slate-200/80 bg-gradient-to-br from-brand/5 via-white to-slate-50 px-5 py-4 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
          <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
            {{ greeting.date }}
          </p>
          <h2 class="mt-0.5 font-display text-lg font-semibold text-slate-800">
            {{ greeting.text }}, <span class="text-brand">{{ user.name }}</span>
          </h2>
          <div class="mt-1.5 flex items-center gap-2">
            <span
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1"
              :class="roleRing"
            >
              {{ roleLabel }}
            </span>
            <span class="text-[11px] text-slate-400">Hệ thống VA QLDA</span>
          </div>
        </div>

        <Link
          href="/work"
          class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-brand/20 bg-white px-3.5 py-2 text-xs font-semibold text-brand shadow-sm transition hover:border-brand/40 hover:bg-brand/5"
        >
          <AppIcon
            name="projects"
            :size="14"
          />
          Dashboard Công Việc
          <AppIcon
            name="arrow-right"
            :size="13"
            class="text-brand/60"
          />
        </Link>
      </div>
    </div>

    <!-- 1. KPI summary strip -->
    <HubSummaryBar :summary="summary" />

    <!-- 2. Activity trend (interactive) -->
    <HubActivityTrendChart
      :trend="charts.activityTrend ?? []"
      class="mb-4"
    />

    <!-- 3. Distributions (+ severity for lead) -->
    <div
      class="mb-4 grid gap-4"
      :class="hasSeverity ? 'lg:grid-cols-3' : 'lg:grid-cols-2'"
    >
      <HubStatusDonut
        title="Trạng thái dự án"
        icon="projects"
        :items="charts.projectStatus ?? []"
        base-href="/projects"
        param-key="status"
        center-label="dự án"
        empty-text="Chưa có dự án"
      />
      <HubStatusDonut
        title="Phân bố công việc"
        icon="task"
        :items="charts.taskStatus ?? []"
        center-label="công việc"
        empty-text="Chưa có công việc"
      />
      <HubSeverityChart
        v-if="hasSeverity"
        :items="charts.blockerSeverity"
      />
    </div>

    <!-- 4. Compliance (lead+) -->
    <div
      v-if="hasCompliance"
      class="mb-5 grid gap-4 lg:grid-cols-2"
    >
      <HubCompliancePanel :compliance="charts.reportCompliance" />
    </div>

    <!-- 5. Module launcher -->
    <section>
      <div class="mb-3 flex items-center gap-2">
        <AppIcon
          name="overview"
          :size="16"
          class="text-brand"
        />
        <h2 class="font-display text-sm font-semibold text-slate-700">
          Truy cập nhanh
        </h2>
        <span class="text-[11px] text-slate-400">— tất cả module theo quyền của bạn</span>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div
          v-for="group in moduleGroups"
          :key="group.key"
          class="card overflow-hidden p-0"
        >
          <!-- Group header -->
          <div
            class="flex items-center gap-2.5 border-b px-4 py-3"
            :class="toneGroupBorder[group.tone] ?? 'border-slate-100 bg-slate-50/40'"
          >
            <span
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg ring-1"
              :class="toneIcon[group.tone] ?? toneIcon.slate"
            >
              <AppIcon
                :name="group.icon"
                :size="15"
              />
            </span>
            <span class="font-display text-[13px] font-semibold text-slate-700">
              {{ group.label }}
            </span>
          </div>

          <!-- Module tiles -->
          <div class="grid grid-cols-2 gap-px bg-slate-100/80">
            <Link
              v-for="mod in group.modules"
              :key="mod.key"
              :href="mod.href"
              class="group flex flex-col gap-2 bg-white px-4 py-3.5 transition hover:bg-slate-50/80"
            >
              <div class="flex items-start justify-between gap-2">
                <span
                  class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1 transition group-hover:shadow-sm"
                  :class="toneIcon[mod.tone] ?? toneIcon.slate"
                >
                  <AppIcon
                    :name="mod.icon"
                    :size="16"
                  />
                </span>
                <!-- Stat badge -->
                <span
                  v-if="mod.stat !== null && mod.stat !== undefined"
                  class="font-display text-xl font-bold tabular-nums leading-none"
                  :class="toneStat[mod.tone] ?? 'text-slate-700'"
                >
                  {{ mod.stat }}
                </span>
              </div>

              <div class="min-w-0">
                <p class="truncate text-[13px] font-medium text-slate-700 group-hover:text-slate-900">
                  {{ mod.label }}
                </p>
                <p class="truncate text-[11px] text-slate-400">
                  {{ mod.statLabel }}
                </p>
              </div>
            </Link>
          </div>
        </div>
      </div>
    </section>
  </AppLayout>
</template>
