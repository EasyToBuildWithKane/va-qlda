<script setup>
import { computed, toRef } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useToast } from '@/shared/composables/useToast';
import { useWeeklyReport } from '@/composables/useWeeklyReport';
import WeeklyReportTimelineNav from './WeeklyReportTimelineNav.vue';
import WeeklyReportHeader from './WeeklyReportHeader.vue';
import WeeklyReportKpiGrid from './WeeklyReportKpiGrid.vue';
import WeeklyReportSectionCard from './WeeklyReportSectionCard.vue';
import WeeklyReportRiskCard from './WeeklyReportRiskCard.vue';
import WeeklyReportFeedbackSummary from './WeeklyReportFeedbackSummary.vue';
import WeeklyReportActivityTimeline from './WeeklyReportActivityTimeline.vue';
import WeeklyReportEmptyState from './WeeklyReportEmptyState.vue';

const props = defineProps({
    projectId: { type: [Number, String], required: true },
    overview: { type: Object, default: () => ({ sprint: null, weeks: [], current_week: 1 }) },
    detail: { type: Object, default: null },
    canGenerate: { type: Boolean, default: false },
});

const toast = useToast();

const {
    processing, pendingWeek, report, sprint, weeks, currentWeekNumber,
    draft, editing, dirty, selectWeek, generateForWeek, regenerate,
    startEdit, cancelEdit, save,
} = useWeeklyReport(props.projectId, {
    overview: toRef(props, 'overview'),
    detail: toRef(props, 'detail'),
});

const CARD_ACCENT = { result: 'emerald', current: 'sky', next: 'brand' };

function sectionByKey(key) {
    return (report.value?.sections ?? []).find((s) => s.section === key)
        ?? { section: key, label: key, icon: 'overview', content: '', editable: true, is_edited: false };
}

const activityContent = computed(() => sectionByKey('activity').content);
const regenerationAvailable = computed(() => report.value?.regeneration_available ?? false);
const canEdit = computed(() => report.value?.can?.update && !report.value?.is_locked);

// Hiển thị empty state khi: chọn tuần chưa có report, hoặc chưa chọn report nào.
const showEmpty = computed(() => !report.value);
const emptyWeekNumber = computed(() => pendingWeek.value ?? currentWeekNumber.value);

function onExport() {
    toast.info?.('Xuất PDF/DOCX sẽ có ở bản cập nhật tới.');
}
</script>

<template>
  <div class="flex h-full min-h-0 w-full flex-col gap-4 overflow-y-auto bg-slate-50 p-4 dark:bg-slate-950 lg:flex-row lg:p-5">
    <WeeklyReportTimelineNav
      :sprint="sprint"
      :weeks="weeks"
      :current-week="currentWeekNumber"
      :active-report-id="report?.id ?? null"
      :pending-week="pendingWeek"
      @select="selectWeek"
    />

    <div class="min-w-0 flex-1">
      <WeeklyReportEmptyState
        v-if="showEmpty"
        :week-number="emptyWeekNumber"
        :can-generate="canGenerate"
        :processing="processing"
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
        />

        <!-- Executive Summary -->
        <section class="rounded-xl border border-brand/20 bg-brand/5 p-4 dark:border-brand/30 dark:bg-brand/10">
          <div class="mb-1.5 flex items-center gap-2">
            <AppIcon
              name="overview"
              :size="15"
              class="text-brand"
            />
            <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-brand">
              Tóm tắt điều hành
            </h3>
          </div>
          <textarea
            v-if="editing"
            v-model="draft.executive_summary"
            rows="3"
            class="w-full resize-y rounded-lg border border-slate-300 bg-white p-2.5 text-sm text-slate-700 focus:border-brand focus:ring-1 focus:ring-brand dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
          />
          <p
            v-else
            class="text-sm leading-relaxed text-slate-700 dark:text-slate-200"
          >
            {{ report.executive_summary || 'Chưa có tóm tắt.' }}
          </p>
        </section>

        <!-- KPI -->
        <WeeklyReportKpiGrid :kpi="report.kpi" />

        <!-- AI Insight -->
        <section
          v-if="report.ai_summary"
          class="flex gap-3 rounded-xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-900 dark:bg-violet-950/40"
        >
          <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:text-violet-300">
            <AppIcon
              name="sparkles"
              :size="16"
            />
          </span>
          <div>
            <h3 class="font-display text-xs font-semibold uppercase tracking-wide text-violet-600 dark:text-violet-300">
              Nhận định
            </h3>
            <p class="mt-0.5 text-sm leading-relaxed text-slate-700 dark:text-slate-200">
              {{ report.ai_summary }}
            </p>
          </div>
        </section>

        <!-- Edit toolbar -->
        <div
          v-if="canEdit"
          class="flex items-center justify-end gap-2"
        >
          <template v-if="editing">
            <span
              v-if="dirty"
              class="mr-auto text-xs text-amber-600"
            >Có thay đổi chưa lưu</span>
            <button
              type="button"
              class="rounded-lg px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
              @click="cancelEdit"
            >
              Hủy
            </button>
            <button
              type="button"
              :disabled="processing || !dirty"
              class="btn-primary inline-flex items-center gap-1.5 text-sm disabled:opacity-60"
              @click="save"
            >
              <AppIcon
                name="save"
                :size="15"
              /> Lưu
            </button>
          </template>
          <button
            v-else
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
            @click="startEdit"
          >
            <AppIcon
              name="edit"
              :size="15"
            /> Chỉnh sửa
          </button>
        </div>

        <!-- 3 main cards -->
        <div class="grid gap-4 lg:grid-cols-3">
          <WeeklyReportSectionCard
            v-for="key in ['result', 'current', 'next']"
            :key="key"
            :section="sectionByKey(key)"
            :model-value="draft.sections[key] ?? ''"
            :editing="editing"
            :accent="CARD_ACCENT[key]"
            @update:model-value="(v) => (draft.sections[key] = v)"
          />
        </div>

        <!-- Risk + Feedback -->
        <div class="grid gap-4 lg:grid-cols-2">
          <WeeklyReportRiskCard :risk="report.meta?.risk" />
          <WeeklyReportFeedbackSummary :feedback="report.meta?.feedback" />
        </div>

        <!-- Activity -->
        <WeeklyReportActivityTimeline :content="activityContent" />
      </div>
    </div>
  </div>
</template>
