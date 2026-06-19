<script setup>
/* eslint-disable vue/no-v-html -- rendered markdown report fields */
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from '@/shared/composables/useToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import StatusBadge from '@/modules/daily-report/components/StatusBadge.vue';
import TaskStatusBadge from '@/Components/TaskStatusBadge.vue';
import GradePill from '@/modules/daily-report/components/GradePill.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import { fields } from '@/modules/daily-report/config/reportConfig';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { dateLongVi } from '@/composables/useFormat';

const confirmDelete = useConfirmDelete();

const props = defineProps({
    report: { type: Object, required: true },
});

const page = usePage();
const toast = useToast();
const submitError = computed(() => page.props.errors?.submit ?? null);

const render = (html) => html || '<span class="text-slate-300">—</span>';
const hasText = (html) => (html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0;

const sections = fields.map((f) => ({ key: f.key, label: f.label, hint: f.hint }));

const scoreDimensions = [
    ['Hoàn thành công việc', 'task_completion'],
    ['Kỹ năng', 'skill_score'],
    ['Thái độ', 'attitude_score'],
    ['Cải tiến (Kaizen)', 'kaizen_score'],
    ['Chuyên môn', 'expertise_score'],
];

const formattedDate = computed(() => dateLongVi(props.report.date));
const hasScore = computed(() => Boolean(props.report.score?.grade));
const hasRejectNotes = computed(
    () => props.report.status === 'draft' && Boolean((props.report.review_notes || '').trim()),
);

const submit = () => {
    router.post(`/daily-reports/${props.report.id}/submit`, {}, {
        preserveScroll: true,
        onError: (errors) => {
            const msg = errors.submit ?? 'Không thể nộp báo cáo. Kiểm tra nội dung và thử lại.';
            toast.error(msg);
        },
    });
};

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
      <PageHeader
        title="Chi tiết báo cáo"
        :subtitle="`${report.employee?.name ?? '—'} · ${formattedDate}`"
        icon="report-history"
        icon-color="sky"
      />
    </template>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="space-y-4 lg:col-span-2">
        <div class="card p-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <h2 class="font-display text-base font-semibold text-slate-800">
                {{ report.title }}
              </h2>
              <p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1">
                  <AppIcon
                    name="calendar"
                    :size="13"
                    class="text-slate-400"
                  />
                  {{ formattedDate }}
                </span>
                <span
                  v-if="report.is_late"
                  class="rounded bg-rose-50 px-1.5 py-0.5 font-medium text-danger"
                >Nộp trễ</span>
              </p>
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
            <div
              v-for="p in report.projects"
              :key="p.id"
            >
              <p class="text-xs font-semibold text-brand">
                {{ p.name }}
              </p>
              <div
                v-if="p.tasks?.length"
                class="mt-1 flex flex-wrap gap-1.5"
              >
                <span
                  v-for="t in p.tasks"
                  :key="`${p.id}-${t.id}`"
                  class="inline-flex items-center gap-1 rounded-md border border-slate-100 bg-slate-50 px-2 py-0.5 text-xs text-slate-700"
                >
                  {{ t.title }}
                  <TaskStatusBadge
                    v-if="t.id"
                    :task-id="t.id"
                    :initial-status="t.status || 'todo'"
                    :snapshot="report.task_status_snapshot ?? []"
                  />
                </span>
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="hasRejectNotes"
          class="card border-l-4 border-warning p-4"
        >
          <p class="flex items-center gap-1.5 text-sm font-semibold text-slate-800">
            <AppIcon
              name="refresh"
              :size="15"
              class="text-warning"
            />
            Lý do trả lại
          </p>
          <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">
            {{ report.review_notes }}
          </p>
        </div>

        <div class="card divide-y divide-slate-100 p-5 sm:p-6">
          <section
            v-for="(s, i) in sections"
            :key="s.key"
            :class="i === 0 ? 'pb-5' : 'py-5 last:pb-0'"
          >
            <div class="mb-2 flex items-center gap-1.5">
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

        <p
          v-if="submitError"
          class="text-sm text-danger"
        >
          {{ submitError }}
        </p>

        <div class="flex flex-wrap gap-2">
          <Link
            v-if="report.can?.update"
            href="/daily-reports/today"
            class="btn-ghost gap-1.5"
          >
            <AppIcon
              name="edit"
              :size="15"
            />
            Sửa bản nháp
          </Link>
          <button
            v-if="report.can?.submit"
            type="button"
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
            />
            Xoá nháp
          </button>
        </div>
      </div>

      <div class="space-y-4">
        <div
          v-if="hasScore"
          class="card p-5 lg:sticky lg:top-4"
        >
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Kết quả đánh giá
          </p>
          <div class="mt-3 flex items-end justify-between gap-3">
            <div>
              <p class="font-display text-3xl font-bold tabular-nums text-brand">
                {{ Number(report.score.total_score ?? 0).toFixed(2) }}
              </p>
              <p class="mt-0.5 text-xs text-slate-500">
                {{ report.score.grade_label }}
              </p>
            </div>
            <GradePill
              :grade="report.score.grade"
              :color="report.score.grade_color"
            />
          </div>

          <dl class="mt-5 space-y-2.5 border-t border-slate-100 pt-4">
            <div
              v-for="[label, key] in scoreDimensions"
              :key="key"
              class="flex items-center justify-between gap-3 text-sm"
            >
              <dt class="text-slate-500">
                {{ label }}
              </dt>
              <dd class="font-semibold tabular-nums text-slate-700">
                {{ Number(report.score[key] ?? 0).toFixed(1) }}
              </dd>
            </div>
          </dl>

          <div
            v-if="report.score.notes"
            class="mt-4 rounded-card border border-sky-100 bg-sky-50/60 p-3"
          >
            <p class="text-[10px] font-bold uppercase tracking-wider text-sky-700/80">
              Nhận xét
            </p>
            <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
              {{ report.score.notes }}
            </p>
          </div>

          <p
            v-if="report.score.reviewer"
            class="mt-4 text-xs text-slate-400"
          >
            Người duyệt: {{ report.score.reviewer.name }}
          </p>
          <p
            v-if="report.reviewed_at"
            class="mt-1 text-xs text-slate-400"
          >
            Duyệt lúc: {{ new Date(report.reviewed_at).toLocaleString('vi-VN') }}
          </p>
        </div>

        <div
          v-else-if="report.status === 'submitted'"
          class="card p-5 text-center lg:sticky lg:top-4"
        >
          <AppIcon
            name="review-reports"
            :size="28"
            class="mx-auto mb-2 text-amber-400"
          />
          <p class="text-sm font-medium text-slate-700">
            Đang chờ duyệt
          </p>
          <p class="mt-1 text-xs text-slate-500">
            Quản lý sẽ chấm điểm và phản hồi sau khi xem báo cáo.
          </p>
        </div>

        <div
          v-else
          class="card p-5 text-center text-sm text-slate-400 lg:sticky lg:top-4"
        >
          <AppIcon
            name="review-reports"
            :size="28"
            class="mx-auto mb-2 text-slate-300"
          />
          Chưa có đánh giá.
        </div>
      </div>
    </div>
  </AppLayout>
</template>
