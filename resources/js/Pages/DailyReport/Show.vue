<script setup>
/* eslint-disable vue/no-v-html -- rendered markdown report fields */
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import StatusBadge from '@/Components/DailyReport/StatusBadge.vue';
import GradePill from '@/Components/DailyReport/GradePill.vue';
import { fields } from '@/modules/daily-report/config/reportConfig';
import { useConfirmDelete } from '@/composables/useConfirmClose';

const confirmDelete = useConfirmDelete();

const props = defineProps({
    report: { type: Object, required: true },
});

// Report content is rich HTML from the editor — render it directly.
const render = (html) => html || '<span class="text-slate-300">—</span>';
const hasText = (html) => (html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0;

// Keep section labels/hints in sync with the editor (Vietnamese, Horenso order).
const sections = fields.map((f) => ({ key: f.key, label: f.label, hint: f.hint }));

const scoreDimensions = [
    ['Hoàn thành công việc', 'task_completion'],
    ['Kỹ năng', 'skill_score'],
    ['Thái độ', 'attitude_score'],
    ['Cải tiến (Kaizen)', 'kaizen_score'],
    ['Chuyên môn', 'expertise_score'],
];

const submit = () => router.post(`/daily-reports/${props.report.id}/submit`);

const remove = () => {
    confirmDelete(
        `Xoá báo cáo nháp "${props.report.title}"? Thao tác không thể hoàn tác.`,
        () => router.delete(`/daily-reports/${props.report.id}`),
    );
};
</script>

<template>
  <Head :title="report.title" />

  <AppLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link
          href="/daily-reports"
          class="grid h-8 w-8 place-items-center rounded-btn text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
          title="Quay lại lịch sử báo cáo"
        >
          <AppIcon
            name="back"
            :size="18"
          />
        </Link>
        <div class="leading-tight">
          <h1 class="font-display font-semibold text-slate-800">
            {{ report.title }}
          </h1>
          <p class="text-xs text-slate-400">
            {{ report.employee?.name }} · {{ report.date }}
          </p>
        </div>
      </div>
    </template>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <!-- Body -->
      <div class="space-y-4 lg:col-span-2">
        <!-- Meta -->
        <div class="card p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2 text-sm text-slate-500">
              <span class="grid h-7 w-7 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand">
                {{ (report.employee?.name || '?').charAt(0) }}
              </span>
              <span class="font-medium text-slate-700">{{ report.employee?.name }}</span>
              <span class="text-slate-300">·</span>
              <span class="inline-flex items-center gap-1">
                <AppIcon
                  name="calendar"
                  :size="14"
                  class="text-slate-400"
                />
                {{ report.date }}
              </span>
              <span
                v-if="report.is_late"
                class="rounded bg-rose-50 px-1.5 py-0.5 text-xs font-medium text-danger"
              >nộp trễ</span>
            </div>
            <StatusBadge
              :label="report.status_label"
              :color="report.status_color"
            />
          </div>

          <div
            v-if="report.projects?.length"
            class="mt-3 space-y-2 border-t border-slate-100 pt-3"
          >
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
              Dự án & công việc
            </p>
            <div
              v-for="p in report.projects"
              :key="p.id"
              class="flex flex-wrap items-center gap-1.5"
            >
              <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand">{{ p.name }}</span>
              <span
                v-for="t in (p.tasks || [])"
                :key="t.id"
                class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs text-slate-600"
              >{{ t.title }}</span>
            </div>
          </div>
        </div>

        <!-- Returned for revision -->
        <div
          v-if="report.review_notes && report.status === 'draft'"
          class="card border-l-4 border-warning p-4"
        >
          <p class="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
            <AppIcon
              name="refresh"
              :size="15"
              class="text-warning"
            /> Báo cáo được trả lại
          </p>
          <p class="mt-1 text-sm text-slate-600">
            {{ report.review_notes }}
          </p>
        </div>

        <!-- Content sections -->
        <div class="card divide-y divide-slate-100 p-6">
          <section
            v-for="(s, i) in sections"
            :key="s.key"
            :class="i === 0 ? 'pb-5' : 'py-5 last:pb-0'"
          >
            <div class="mb-1.5 flex items-center gap-1.5">
              <span
                class="inline-block h-1.5 w-1.5 rounded-full"
                :class="hasText(report[s.key]) ? 'bg-success' : 'bg-slate-300'"
              />
              <h3 class="text-sm font-semibold text-slate-700">
                {{ s.label }}
              </h3>
            </div>
            <div
              class="rich-content text-sm text-slate-600"
              v-html="render(report[s.key])"
            />
          </section>
        </div>

        <div class="flex gap-2">
          <Link
            v-if="report.can?.update"
            href="/daily-reports/today"
            class="btn-ghost gap-1.5"
          >
            <AppIcon
              name="edit"
              :size="15"
            /> Sửa bản nháp
          </Link>
          <button
            v-if="report.can?.submit"
            class="btn-primary"
            @click="submit"
          >
            Nộp duyệt
          </button>
          <button
            v-if="report.can?.delete"
            type="button"
            class="btn-ghost gap-1.5 text-danger hover:bg-rose-50"
            @click="remove"
          >
            <AppIcon
              name="trash"
              :size="15"
            /> Xoá nháp
          </button>
        </div>
      </div>

      <!-- Score sidebar -->
      <div class="space-y-4">
        <div
          v-if="report.score"
          class="card p-6 lg:sticky lg:top-4"
        >
          <div class="mb-4 flex items-center justify-between">
            <h3 class="font-display font-semibold text-slate-800">
              Đánh giá
            </h3>
            <div class="flex items-center gap-2">
              <span class="font-display text-2xl font-bold text-brand">
                {{ Number(report.score.total_score ?? 0).toFixed(2) }}
              </span>
              <GradePill
                :grade="report.score.grade"
                :color="report.score.grade_color"
              />
            </div>
          </div>
          <dl class="space-y-2">
            <div
              v-for="[label, key] in scoreDimensions"
              :key="key"
              class="flex items-center justify-between text-sm"
            >
              <dt class="text-slate-500">
                {{ label }}
              </dt>
              <dd class="font-medium text-slate-700">
                {{ Number(report.score[key] ?? 0).toFixed(1) }}
              </dd>
            </div>
          </dl>
          <div
            v-if="report.score.notes"
            class="mt-4 border-t border-slate-100 pt-3"
          >
            <p class="mb-1 text-xs text-slate-400">
              Nhận xét của người duyệt
            </p>
            <p class="text-sm text-slate-600">
              {{ report.score.notes }}
            </p>
          </div>
          <p
            v-if="report.score.reviewer"
            class="mt-3 text-xs text-slate-400"
          >
            Người duyệt: {{ report.score.reviewer.name }}
          </p>
        </div>

        <div
          v-else
          class="card p-6 text-center text-sm text-slate-400 lg:sticky lg:top-4"
        >
          <AppIcon
            name="review-reports"
            :size="28"
            class="mx-auto mb-2 text-slate-300"
          />
          Báo cáo chưa được chấm điểm.
        </div>
      </div>
    </div>
  </AppLayout>
</template>
