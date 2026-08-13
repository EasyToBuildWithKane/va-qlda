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
    overview: { type: Object, default: () => ({ sprint: null, reports: [], default_start: '', default_end: '' }) },
    detail: { type: Object, default: null },
    canGenerate: { type: Boolean, default: false },
    /** Nhúng trong tab Tổng quan (cuộn theo trang, không khung h-full). */
    embedded: { type: Boolean, default: false },
    /** Tab hiện tại — giữ nguyên khi chọn kỳ / thao tác Inertia. */
    activeTab: { type: String, default: 'weekly' },
});

const {
    processing, report, sectionList, sprint, reports, periodStart, periodEnd,
    draft, editing, dirty, selectReport, selectPeriod, generateForPeriod, regenerate,
    startEdit, cancelEdit, save, submit, approve, reject,
} = useWeeklyReport(props.projectId, {
    overview: toRef(props, 'overview'),
    detail: toRef(props, 'detail'),
    tab: toRef(props, 'activeTab'),
});

function sectionByKey(key) {
    return sectionList.value.find((s) => s.section === key)
        ?? { section: key, label: key, icon: 'overview', content: '', editable: true, is_edited: false };
}

const regenerationAvailable = computed(() => report.value?.regeneration_available ?? false);
const canEdit = computed(() => report.value?.can?.update && !report.value?.is_locked);

const showEmpty = computed(() => !report.value);

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
        :reports="reports"
        :period-start="periodStart"
        :period-end="periodEnd"
        :active-report-id="report?.id ?? null"
        :hide-sprint-scope="embedded"
        @select-report="selectReport"
        @update-period="selectPeriod"
      />
    </div>

    <div
      class="p-4 sm:p-5"
      :class="embedded ? '' : 'min-h-0 flex-1 overflow-y-auto'"
    >
      <WeeklyReportEmptyState
        v-if="showEmpty"
        :period-start="periodStart"
        :period-end="periodEnd"
        :can-generate="canGenerate"
        :processing="processing"
        @generate="() => generateForPeriod()"
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

        <WeeklyReportKpiStrip :kpi="report.kpi" />

        <WeeklyReportExecutiveCard
          :executive-summary="report.executive_summary"
          :ai-summary="report.ai_summary"
          :model-value="draft.executive_summary"
          :editing="editing"
          :can-edit="canEdit"
          :outcomes="report.meta?.outcomes ?? []"
          @update:model-value="(v) => (draft.executive_summary = v)"
          @edit="startEdit"
        />

        <div class="grid gap-4 lg:grid-cols-3">
          <WeeklyReportSectionCard
            v-for="key in ['result', 'current', 'next']"
            :key="key"
            :section="sectionByKey(key)"
            :model-value="draft.sections[key] ?? ''"
            :editing="editing"
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
