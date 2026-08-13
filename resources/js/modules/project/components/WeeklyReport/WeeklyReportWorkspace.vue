<script setup>
import { computed, toRef } from 'vue';
import { useWeeklyReport } from '@/composables/useWeeklyReport';
import WeeklyReportTimelineNav from './WeeklyReportTimelineNav.vue';
import WeeklyReportHeader from './WeeklyReportHeader.vue';
import WeeklyReportExecutiveCard from './WeeklyReportExecutiveCard.vue';
import WeeklyReportKpiStrip from './WeeklyReportKpiStrip.vue';
import WeeklyReportSectionCard from './WeeklyReportSectionCard.vue';
import WeeklyReportRiskCard from './WeeklyReportRiskCard.vue';
import WeeklyReportVersionPanel from './WeeklyReportVersionPanel.vue';
import WeeklyReportEmptyState from './WeeklyReportEmptyState.vue';

const props = defineProps({
    projectId: { type: [Number, String], required: true },
    overview: { type: Object, default: () => ({ sprint: null, weeks: [], current_week: 1 }) },
    detail: { type: Object, default: null },
    canGenerate: { type: Boolean, default: false },
    /** Nhúng trong tab Tổng quan (cuộn theo trang, không khung h-full). */
    embedded: { type: Boolean, default: false },
    /** Tab hiện tại — giữ nguyên khi chọn tuần / thao tác Inertia. */
    activeTab: { type: String, default: 'weekly' },
});

const {
    processing, pendingWeek, report, sectionList, sprint, weeks, currentWeekNumber,
    draft, editing, dirty, selectWeek, generateForWeek, regenerate,
    startEdit, cancelEdit, save, submit, approve, reject,
} = useWeeklyReport(props.projectId, {
    overview: toRef(props, 'overview'),
    detail: toRef(props, 'detail'),
    tab: toRef(props, 'activeTab'),
});

const CARD_ACCENT = { result: 'emerald', current: 'sky', next: 'brand' };

function sectionByKey(key) {
    return sectionList.value.find((s) => s.section === key)
        ?? { section: key, label: key, icon: 'overview', content: '', editable: true, is_edited: false };
}

const regenerationAvailable = computed(() => report.value?.regeneration_available ?? false);
const canEdit = computed(() => report.value?.can?.update && !report.value?.is_locked);
const engine = computed(() => props.overview?.engine ?? { mode: 'heuristic' });
const reportEngine = computed(() => report.value?.meta?.engine ?? engine.value.mode);

const showEmpty = computed(() => !report.value);
const emptyWeekNumber = computed(() => pendingWeek.value ?? currentWeekNumber.value);

function onExport(format) {
    if (!report.value) return;
    const name = format === 'docx' ? 'projects.weekly-reports.export.docx' : 'projects.weekly-reports.export.pdf';
    window.open(route(name, [props.projectId, report.value.id]), '_blank');
}
</script>

<template>
  <div
    class="flex w-full flex-col"
    :class="embedded
      ? 'overflow-hidden rounded-2xl border border-slate-200/80 bg-slate-50 shadow-sm dark:border-slate-700/80 dark:bg-slate-950'
      : 'h-full min-h-0 overflow-hidden bg-slate-50 dark:bg-slate-950'"
  >
    <div class="shrink-0 border-b border-slate-200/80 dark:border-slate-800">
      <WeeklyReportTimelineNav
        :sprint="sprint"
        :weeks="weeks"
        :current-week="currentWeekNumber"
        :active-report-id="report?.id ?? null"
        :pending-week="pendingWeek"
        :hide-sprint-scope="embedded"
        :engine="engine"
        @select="selectWeek"
      />
    </div>

    <div
      class="p-4 sm:p-5"
      :class="embedded ? '' : 'min-h-0 flex-1 overflow-y-auto'"
    >
      <WeeklyReportEmptyState
        v-if="showEmpty"
        :week-number="emptyWeekNumber"
        :can-generate="canGenerate"
        :processing="processing"
        :engine="engine"
        @generate="generateForWeek"
      />

      <div
        v-else
        class="space-y-4"
      >
        <WeeklyReportHeader
          :report="report"
          :can-generate="canGenerate"
          :regeneration-available="regenerationAvailable"
          :processing="processing"
          @regenerate="regenerate({ preserve: true })"
          @export="onExport"
          @submit="submit"
          @approve="approve"
          @reject="reject"
        />

        <WeeklyReportExecutiveCard
          :executive-summary="report.executive_summary"
          :ai-summary="report.ai_summary"
          :model-value="draft.executive_summary"
          :editing="editing"
          :can-edit="canEdit"
          :engine="reportEngine"
          @update:model-value="(v) => (draft.executive_summary = v)"
          @edit="startEdit"
        />

        <WeeklyReportKpiStrip :kpi="report.kpi" />

        <div class="grid gap-4 lg:grid-cols-3">
          <WeeklyReportSectionCard
            v-for="key in ['result', 'current', 'next']"
            :key="key"
            :section="sectionByKey(key)"
            :model-value="draft.sections[key] ?? ''"
            :editing="editing"
            :accent="CARD_ACCENT[key]"
            :can-edit="canEdit"
            :show-save-cancel="key === 'result'"
            :dirty="dirty"
            :processing="processing"
            @update:model-value="(v) => (draft.sections[key] = v)"
            @edit="startEdit"
            @save="save"
            @cancel="cancelEdit"
          />
        </div>

        <WeeklyReportRiskCard :risk="report.meta?.risk" />

        <WeeklyReportVersionPanel :versions="report.versions ?? []" />
      </div>
    </div>
  </div>
</template>
