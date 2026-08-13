<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import PerformanceFilterBar from '@/modules/performance/components/PerformanceFilterBar.vue';
import PerformanceAuditSummaryBar from '@/modules/performance/components/PerformanceAuditSummaryBar.vue';
import AuditTimeline from '@/modules/performance/components/AuditTimeline.vue';
import { usePerformanceExport } from '@/modules/performance/composables/usePerformanceExport.js';

const props = defineProps({
    employee: { type: Object, required: true },
    filter: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    audit: { type: Object, default: null },
});

const { exportAudit } = usePerformanceExport();

const processing = ref(false);
let offStart;
let offFinish;
onMounted(() => {
    offStart = router.on('start', () => { processing.value = true; });
    offFinish = router.on('finish', () => { processing.value = false; });
});
onUnmounted(() => { offStart?.(); offFinish?.(); });

function onExportAudit() {
    if (props.audit) exportAudit(props.audit, props.filter);
}

const memberSubtitle = computed(() => {
    const parts = [
        props.employee?.role || props.audit?.member?.role,
        props.audit?.member?.unitName || props.filter?.department_name,
    ].filter(Boolean);
    return parts.join(' · ');
});

const backHref = computed(() => {
    const params = {};
    const f = props.filter;
    if (f.period) params.period = f.period;
    if (f.date) params.date = f.date;
    if (f.department) params.department = f.department;
    if (f.team) params.team = f.team;
    return route('performance.audit', params);
});
</script>

<template>
  <Head :title="`Audit — ${employee.name}`" />

  <AppLayout>
    <template #header>
      <PageHeader
        :title="employee.name"
        :subtitle="memberSubtitle ? `${memberSubtitle} — ${filter.label || ''}` : `Timeline cam kết & kết quả — ${filter.label || ''}`"
        icon="leaderboard"
        icon-color="brand"
        :back-href="backHref"
      />
    </template>

    <PerformanceAuditSummaryBar
      v-if="audit"
      mode="detail"
      :summary="audit.summary"
    />

    <PerformanceFilterBar
      :filter="filter"
      :options="options"
      :require-member="false"
      :processing="processing"
      @export-excel="onExportAudit"
    />

    <template v-if="audit">
      <section
        class="card mb-4 flex flex-wrap items-center gap-4 p-5 transition-opacity"
        :class="processing ? 'opacity-60' : 'opacity-100'"
      >
        <Avatar
          :name="employee.name"
          :src="employee.avatar || audit.member?.avatar"
          :size="52"
        />
        <div class="min-w-0">
          <h2 class="font-display text-lg font-semibold text-slate-900">
            {{ employee.name }}
          </h2>
          <p
            v-if="memberSubtitle"
            class="text-[13px] text-slate-500"
          >
            {{ memberSubtitle }}
          </p>
        </div>
      </section>

      <div
        class="transition-opacity"
        :class="processing ? 'opacity-60' : 'opacity-100'"
      >
        <AuditTimeline :weeks="audit.weeks" />
      </div>
    </template>
  </AppLayout>
</template>
