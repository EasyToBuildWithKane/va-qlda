<script setup>
/* eslint-disable vue/no-v-html -- rendered markdown report fields */
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from '@/shared/composables/useToast';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import StatusBadge from '@/modules/daily-report/components/StatusBadge.vue';
import TaskStatusBadge from '@/Components/TaskStatusBadge.vue';
import GradePill from '@/modules/daily-report/components/GradePill.vue';
import ReportAuditTimeline from '@/modules/daily-report/components/ReportAuditTimeline.vue';
import RecallButton from '@/modules/daily-report/components/RecallButton.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import { fields, pillars } from '@/modules/daily-report/config/reportConfig';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { useDialog } from '@/composables/useDialog';
import { dateLongVi } from '@/composables/useFormat';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { isRoutineProjectEntry } from '@/modules/daily-report/constants/routineWork';
import { baocaoFieldsMirrorSelectedTasks } from '@/modules/daily-report/utils/taskDerivedBaocaoHtml';

const confirmDelete = useConfirmDelete();
const dialog = useDialog();

const props = defineProps({
    report: { type: Object, required: true },
    timeline: { type: Array, default: () => [] },
});

const page = usePage();
const toast = useToast();
const submitError = computed(() => page.props.errors?.submit ?? null);

const reportProjects = computed(() => props.report.projects ?? []);
const reportRealProjects = computed(() => reportProjects.value.filter((p) => !isRoutineProjectEntry(p)));
const reportRoutineTasks = computed(() => {
    const routine = reportProjects.value.find(isRoutineProjectEntry);
    return routine?.tasks ?? [];
});
const hasWorkScope = computed(
    () => reportRealProjects.value.length > 0 || reportRoutineTasks.value.length > 0,
);

/** Tránh lặp danh sách task (phạm vi) với Mục tiêu/Tiến độ đã auto-fill từ Today. */
const showStructuredWorkScope = computed(() => {
    if (!hasWorkScope.value) return false;
    return !baocaoFieldsMirrorSelectedTasks(
        props.report.projects,
        props.report.goals_today,
        props.report.progress_update,
    );
});

const render = (html) => html || `<span class="text-slate-400">${EMPTY_LABELS.generic}</span>`;
const hasText = (html) => (html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0;

const scoreDimensions = [
    ['Hoàn thành công việc', 'task_completion'],
    ['Kỹ năng', 'skill_score'],
    ['Thái độ', 'attitude_score'],
    ['Cải tiến (Kaizen)', 'kaizen_score'],
    ['Chuyên môn', 'expertise_score'],
];

const pillarTone = Object.fromEntries(pillars.map((p) => [p.key, p.color]));

const PILLAR_ACCENT = {
    brand: { ring: 'ring-brand/20', bar: 'bg-brand', chip: 'bg-brand/10 text-brand' },
    amber: { ring: 'ring-amber-200/80', bar: 'bg-amber-400', chip: 'bg-amber-50 text-amber-800' },
    emerald: { ring: 'ring-emerald-200/80', bar: 'bg-emerald-400', chip: 'bg-emerald-50 text-emerald-800' },
    sky: { ring: 'ring-sky-200/80', bar: 'bg-sky-400', chip: 'bg-sky-50 text-sky-800' },
};

const contentGroups = computed(() =>
    pillars.map((pillar) => ({
        pillar,
        accent: PILLAR_ACCENT[pillarTone[pillar.key]] ?? PILLAR_ACCENT.brand,
        fields: fields.filter((f) => f.pillar === pillar.key),
    })),
);

const formattedDate = computed(() => dateLongVi(props.report.date));
const employeeName = computed(() => displayOrEmpty(props.report.employee?.name, EMPTY_LABELS.notUpdated));
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

const canRecall = computed(() => Boolean(props.report.can?.recall));
const isDraft = computed(() => props.report.status === 'draft');
const recalling = ref(false);

const recall = async () => {
    if (recalling.value) return;
    const reason = await dialog.prompt({
        title: 'Rút lại báo cáo?',
        message: 'Báo cáo sẽ trở về bản nháp để bạn chỉnh sửa và nộp lại. Người duyệt sẽ được thông báo. Nêu lý do thu hồi (không bắt buộc):',
        placeholder: 'Ví dụ: bổ sung kết quả còn thiếu…',
        confirmText: 'Rút lại',
        cancelText: 'Huỷ',
    });
    if (reason === null) return;
    recalling.value = true;
    router.post(`/daily-reports/${props.report.id}/recall`, { reason }, {
        preserveScroll: true,
        onFinish: () => { recalling.value = false; },
    });
};
</script>

<template>
  <Head :title="report.title" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Chi tiết báo cáo"
        :subtitle="`${employeeName} · ${formattedDate}`"
        icon="report-history"
        icon-color="sky"
        back-href="/daily-reports"
      />
    </template>

    <div class="mx-auto w-full min-w-0 max-w-6xl space-y-5 overflow-x-clip">
      <!-- Hero -->
      <div class="card overflow-hidden p-4 sm:p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex min-w-0 items-start gap-3">
            <Avatar
              :name="report.employee?.name ?? '?'"
              :src="report.employee?.avatar_path"
              :size="44"
            />
            <div class="min-w-0 flex-1">
              <h2 class="font-display text-lg font-semibold leading-snug text-slate-800 sm:text-xl">
                {{ report.title }}
              </h2>
              <p class="mt-1 truncate text-sm text-slate-600">
                {{ employeeName }}
              </p>
              <p
                v-if="report.employee?.role_title"
                class="mt-0.5 truncate text-xs text-slate-400"
              >
                {{ report.employee.role_title }}
              </p>
              <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span class="inline-flex max-w-full items-center gap-1">
                  <AppIcon
                    name="calendar"
                    :size="13"
                    class="shrink-0 text-slate-400"
                  />
                  <span class="truncate">{{ formattedDate }}</span>
                </span>
                <span
                  v-if="report.is_late"
                  class="shrink-0 rounded bg-rose-50 px-1.5 py-0.5 font-medium text-danger"
                >Nộp trễ</span>
                <span
                  v-if="report.recall_count > 0"
                  class="inline-flex max-w-full shrink-0 items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 font-medium text-amber-700"
                >
                  <AppIcon
                    name="back"
                    :size="12"
                  />
                  Đã rút lại {{ report.recall_count }} lần
                </span>
              </div>
            </div>
          </div>
          <div class="flex shrink-0 flex-wrap items-center gap-2 sm:flex-col sm:items-end">
            <StatusBadge
              :label="report.status_label"
              :color="report.status_color"
            />
            <div
              v-if="hasScore"
              class="inline-flex items-center gap-2"
            >
              <GradePill
                :grade="report.score.grade"
                :color="report.score.grade_color"
              />
              <span class="font-display text-lg font-bold tabular-nums text-brand">
                {{ Number(report.score.total_score ?? 0).toFixed(1) }}
              </span>
            </div>
          </div>
        </div>

        <div
          v-if="showStructuredWorkScope"
          class="mt-4 space-y-4 border-t border-slate-100 pt-4"
        >
          <div
            v-if="reportRealProjects.length"
            class="space-y-3"
          >
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
              Dự án
            </p>
            <div
              v-for="p in reportRealProjects"
              :key="p.id"
              class="min-w-0 rounded-lg border border-slate-100 bg-slate-50/70 p-3"
            >
              <p class="truncate text-sm font-semibold text-brand">
                {{ p.name }}
              </p>
              <ul
                v-if="p.tasks?.length"
                class="mt-2 space-y-2"
              >
                <li
                  v-for="t in p.tasks"
                  :key="`${p.id}-${t.id}`"
                  class="flex min-w-0 flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3"
                >
                  <span class="min-w-0 truncate text-sm text-slate-700">
                    {{ t.title }}
                  </span>
                  <TaskStatusBadge
                    v-if="t.id > 0"
                    class="shrink-0 self-start sm:self-center"
                    :task-id="t.id"
                    :initial-status="t.status || 'todo'"
                    :snapshot="report.task_status_snapshot ?? []"
                  />
                </li>
              </ul>
            </div>
          </div>

          <div
            v-if="reportRoutineTasks.length"
            class="space-y-2"
          >
            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700/90">
              Công việc thường xuyên
            </p>
            <p class="text-xs text-slate-500">
              Không gắn với dự án cụ thể.
            </p>
            <ul class="space-y-1.5 rounded-lg border border-amber-200/80 bg-amber-50/40 p-3">
              <li
                v-for="(t, idx) in reportRoutineTasks"
                :key="`routine-${idx}-${t.title}`"
                class="text-sm text-slate-700"
              >
                {{ t.title }}
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div class="grid min-w-0 grid-cols-1 gap-5 lg:grid-cols-3">
        <!-- Main -->
        <div class="min-w-0 space-y-4 lg:col-span-2 lg:order-1">
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
            <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-600">
              {{ report.review_notes }}
            </p>
          </div>

          <div
            v-for="group in contentGroups"
            :key="group.pillar.key"
            class="card min-w-0 overflow-hidden ring-1 ring-inset"
            :class="group.accent.ring"
          >
            <div
              class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5 sm:px-5"
              :class="group.accent.chip"
            >
              <span
                class="h-2 w-2 shrink-0 rounded-full"
                :class="group.accent.bar"
              />
              <span class="text-xs font-bold uppercase tracking-wide">
                {{ group.pillar.title }}
              </span>
              <span class="truncate text-[11px] font-normal opacity-80">
                {{ group.pillar.subtitle }}
              </span>
            </div>
            <div class="divide-y divide-slate-100">
              <section
                v-for="f in group.fields"
                :key="f.key"
                class="min-w-0 px-4 py-4 sm:px-5"
              >
                <div class="mb-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                  <span
                    class="inline-block h-1.5 w-1.5 shrink-0 rounded-full"
                    :class="hasText(report[f.key]) ? 'bg-success' : 'bg-slate-300'"
                  />
                  <h3 class="text-sm font-semibold text-slate-700">
                    {{ f.label }}
                  </h3>
                  <span
                    v-if="f.required"
                    class="text-[10px] font-medium uppercase text-slate-400"
                  >Bắt buộc</span>
                </div>
                <div
                  class="rich-content min-w-0 max-w-full text-sm text-slate-600"
                  v-html="render(report[f.key])"
                />
              </section>
            </div>
          </div>

          <p
            v-if="submitError"
            class="text-sm text-danger"
          >
            {{ submitError }}
          </p>

          <div
            v-if="isDraft && (report.can?.update || report.can?.submit || report.can?.delete)"
            class="flex flex-wrap gap-2"
          >
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
                name="delete"
                :size="15"
              />
              Xoá nháp
            </button>
          </div>
          <div
            v-else-if="canRecall"
            class="flex flex-wrap gap-2"
          >
            <RecallButton
              :recalling="recalling"
              @recall="recall"
            />
          </div>
        </div>

        <!-- Sidebar -->
        <aside class="min-w-0 space-y-4 lg:order-2">
          <div
            v-if="hasScore"
            class="card p-5"
          >
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
              Kết quả đánh giá
            </p>
            <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
              <div class="min-w-0">
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
                class="flex min-w-0 items-start justify-between gap-3 text-sm"
              >
                <dt class="min-w-0 flex-1 text-slate-500">
                  {{ label }}
                </dt>
                <dd class="shrink-0 font-semibold tabular-nums text-slate-700">
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
              <p class="mt-1.5 whitespace-pre-wrap break-words text-sm leading-relaxed text-slate-700">
                {{ report.score.notes }}
              </p>
            </div>

            <p
              v-if="report.score.reviewer"
              class="mt-4 break-words text-xs text-slate-400"
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
            class="card p-5 text-center"
          >
            <AppIcon
              name="review-reports"
              :size="28"
              class="mx-auto mb-2 text-amber-400"
            />
            <p class="text-sm font-medium text-slate-700">
              Đang chờ duyệt
            </p>
            <p class="mt-1 text-xs leading-relaxed text-slate-500">
              Quản lý sẽ chấm điểm và phản hồi sau khi xem báo cáo.
            </p>
            <Link
              href="/daily-reports/review"
              class="btn-ghost mx-auto mt-3 gap-1.5 text-xs"
            >
              Mở hàng chờ duyệt
              <AppIcon
                name="chevron-right"
                :size="14"
              />
            </Link>
          </div>

          <div
            v-else
            class="card p-5 text-center text-sm text-slate-400"
          >
            <AppIcon
              name="review-reports"
              :size="28"
              class="mx-auto mb-2 text-slate-300"
            />
            Chưa có đánh giá.
          </div>

          <ReportAuditTimeline :events="timeline" />
        </aside>
      </div>
    </div>
  </AppLayout>
</template>
