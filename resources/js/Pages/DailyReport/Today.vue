<script setup>
/**
 * @typedef {import('@/types/dailyReport').ReportProjectLink} ReportProjectLink
 */
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import RichTextField from '@/modules/daily-report/components/RichTextField.vue';
import ProjectSelect from '@/modules/daily-report/components/ProjectSelect.vue';
import TemplateGallery from '@/modules/daily-report/components/TemplateGallery.vue';
import InfoTooltip from '@/modules/daily-report/components/InfoTooltip.vue';
import RecallButton from '@/modules/daily-report/components/RecallButton.vue';
import { pillars, fields, builtinTemplates } from '@/modules/daily-report/config/reportConfig';
import { useDialog } from '@/composables/useDialog';
import { date, dateLongVi } from '@/composables/useFormat';
import { mergeSpawnedTaskIds } from '@/types/dailyReport';
import { useToast } from '@/shared/composables/useToast';
import {
    ROUTINE_PROJECT_NAME,
    isRoutineProjectEntry,
} from '@/modules/daily-report/constants/routineWork';

const dialog = useDialog();
const toast = useToast();

const props = defineProps({
    report: { type: Object, default: null },
    today: { type: String, required: true },
    projectOptions: { type: Array, default: () => [] },
});

const page = usePage();
const isEditing = computed(() => props.report !== null);
const editable = computed(() => !props.report || props.report.status === 'draft');

const reportDate = computed(() => props.report?.date ?? props.today);

// Auto-generated title prefix for a new report.
const titlePrefix = computed(
    () => `Báo cáo ngày ${date(reportDate.value)}`,
);

const form = useForm({
    date: props.report?.date ?? props.today,
    title: props.report?.title ?? titlePrefix.value,
    projects: props.report?.projects ?? [],
    goals_today: props.report?.goals_today ?? '',
    progress_update: props.report?.progress_update ?? '',
    blockers: props.report?.blockers ?? '',
    improvement_suggestions: props.report?.improvement_suggestions ?? '',
    plan_tomorrow: props.report?.plan_tomorrow ?? '',
});

// ---- Derived state --------------------------------------------------------
const contentKeys = fields.map((f) => f.key);
const hasText = (html) => (html || '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length > 0;
const isFilled = (key) => hasText(form[key]);
const pillarFields = (key) => fields.filter((f) => f.pillar === key);
const filledInPillar = (key) => pillarFields(key).filter((f) => isFilled(f.key)).length;

const filledCount = computed(() => contentKeys.filter(isFilled).length);
const progressPct = computed(() => Math.round((filledCount.value / contentKeys.length) * 100));

const missingRequired = computed(() =>
    fields.filter((f) => f.required && !isFilled(f.key)).map((f) => f.label),
);
const titleOk = computed(() => form.title.trim().length > 0);
const readyToSubmit = computed(() => titleOk.value && missingRequired.value.length === 0);

// ---- Submit-attempt state (chips only shown after first failed submit) ---
const submitAttempted = ref(false);

const missingItems = computed(() => {
    const items = [];
    if (!titleOk.value) items.push({ label: 'Tiêu đề', sectionKey: 'info' });
    fields
        .filter((f) => f.required && !isFilled(f.key))
        .forEach((f) => items.push({ label: f.label, sectionKey: f.pillar }));
    return items;
});
const missingLabel = computed(() => missingItems.value.map((m) => m.label).join(', '));

const scrollToSection = (sectionKey) => {
    const el = document.getElementById(`section-${sectionKey}`);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

// ---- Pillar accent styles -------------------------------------------------
const PILLAR_ACCENT = {
    baocao:  { dot: 'bg-brand',       bg: 'bg-brand/[0.03]',    border: 'border-brand/20' },
    lienlac: { dot: 'bg-amber-500',   bg: 'bg-amber-50/50',     border: 'border-amber-200/60' },
    traodoi: { dot: 'bg-emerald-500', bg: 'bg-emerald-50/50',   border: 'border-emerald-200/60' },
    kehoach: { dot: 'bg-sky-500',     bg: 'bg-sky-50/50',       border: 'border-sky-200/60' },
};
const pillarDotClass  = (key) => PILLAR_ACCENT[key]?.dot    ?? 'bg-slate-400';
const pillarHeaderBg  = (key) => PILLAR_ACCENT[key]?.bg     ?? 'bg-slate-50/60';
const pillarHeaderBorder = (key) => PILLAR_ACCENT[key]?.border ?? 'border-slate-100';

const pillarFillClass = (key) => {
    const total  = pillarFields(key).length;
    const filled = filledInPillar(key);
    if (filled === total) return 'bg-emerald-100 text-emerald-700';
    if (pillarFields(key).some((f) => f.required && !isFilled(f.key))) return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-500';
};

// ---- Auto-fill chosen tasks into the report fields -----------------------
const taskStatusLabels = {
    todo: 'Cần làm', in_progress: 'Đang làm', in_review: 'Đang review',
    done: 'Hoàn thành', blocked: 'Bị chặn',
};
const escapeHtml = (s) =>
    String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const taskStatusOf = (projectId, taskId, inlineStatus) => {
    if (isRoutineProjectEntry({ id: projectId })) {
        return inlineStatus ?? 'todo';
    }
    return props.projectOptions.find((o) => o.id === projectId)?.tasks
        ?.find((t) => t.id === taskId)?.status ?? null;
};

const buildTaskHtml = (predicate, showStatus = true) => {
    const blocks = [];
    for (const p of form.projects) {
        const tasks = (p.tasks || []).filter((t) =>
            predicate(taskStatusOf(p.id, t.id, t.status)),
        );
        if (!tasks.length) continue;
        const items = tasks.map((t) => {
            const st = taskStatusOf(p.id, t.id, t.status);
            const tag = showStatus && st ? ` — ${taskStatusLabels[st] || st}` : '';
            return `<li>${escapeHtml(t.title)}${tag}</li>`;
        }).join('');
        const heading = isRoutineProjectEntry(p)
            ? ROUTINE_PROJECT_NAME
            : p.name;
        blocks.push(`<p><strong>${escapeHtml(heading)}</strong></p><ul>${items}</ul>`);
    }
    return blocks.join('');
};

const isTodo = (st) => st === 'todo';
const buildGoalsHtml    = () => buildTaskHtml(isTodo, false);
const buildProgressHtml = () => buildTaskHtml((st) => !isTodo(st), true);

const lastAutoGoals    = ref(buildGoalsHtml());
const lastAutoProgress = ref(buildProgressHtml());

const syncField = (key, lastRef, html) => {
    const current = (form[key] || '').trim();
    if (current === '' || current === lastRef.value.trim()) {
        form[key] = html;
    }
    lastRef.value = html;
};

watch(
    () => form.projects,
    () => {
        syncField('goals_today', lastAutoGoals, buildGoalsHtml());
        syncField('progress_update', lastAutoProgress, buildProgressHtml());
    },
    { deep: true },
);

const taskStatusSnapshot = computed(
    () => props.report?.task_status_snapshot ?? [],
);

const formattedToday = computed(() => dateLongVi(reportDate.value));

const statusVi = computed(() => {
    const map = {
        draft:     { label: 'Bản nháp',   cls: 'bg-slate-100 text-slate-600' },
        submitted: { label: 'Chờ duyệt',  cls: 'bg-amber-100 text-amber-700' },
        reviewed:  { label: 'Đã duyệt',   cls: 'bg-emerald-100 text-emerald-700' },
    };
    return map[props.report?.status] ?? { label: 'Chưa lưu', cls: 'bg-slate-100 text-slate-500' };
});

// ---- Templates ------------------------------------------------------------
const galleryOpen = ref(false);
const galleryTab  = ref('builtin');
const storageKey  = computed(() => `va-qlda.report-templates.${page.props.auth?.user?.id ?? 'guest'}`);
const contentForSave = computed(() => Object.fromEntries(contentKeys.map((k) => [k, form[k]])));

const openGallery = (tab = 'builtin') => {
    galleryTab.value  = tab;
    galleryOpen.value = true;
};

const applyTemplate = async (map) => {
    const willOverwrite = contentKeys.some((k) => k in map && isFilled(k) && map[k] !== form[k]);
    if (willOverwrite) {
        const ok = await dialog.confirm({
            title:       'Áp dụng mẫu?',
            message:     'Thao tác này sẽ ghi đè nội dung đang có ở một số mục. Bạn có chắc chắn?',
            confirmText: 'Áp dụng',
            cancelText:  'Huỷ',
        });
        if (!ok) return;
    }
    contentKeys.forEach((k) => {
        if (k in map) form[k] = map[k];
    });
};

// ---- Persistence ----------------------------------------------------------
/** @param {ReportProjectLink[]|undefined} serverProjects */
const applyServerSpawnIds = (serverProjects) => {
    mergeSpawnedTaskIds(form.projects, serverProjects);
};

const afterSaveSuccess = () => {
    lastSavedAt.value = new Date();
    router.reload({
        only: ['report'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            applyServerSpawnIds(props.report?.projects);
        },
    });
};

watch(
    () => props.report?.projects,
    (serverProjects) => {
        if (!isEditing.value) return;
        applyServerSpawnIds(serverProjects);
    },
    { deep: true },
);

const save = () => {
    if (isEditing.value) {
        form.put(`/daily-reports/${props.report.id}`, {
            preserveScroll: true,
            onSuccess: afterSaveSuccess,
        });
    } else {
        form.post('/daily-reports');
    }
};

const submit = () => {
    if (!isEditing.value) return;
    if (!readyToSubmit.value) {
        submitAttempted.value = true;
        toast.error(`Chưa đủ điều kiện nộp. Còn thiếu: ${missingLabel.value}.`);
        return;
    }
    form.put(`/daily-reports/${props.report.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            router.post(`/daily-reports/${props.report.id}/submit`, {}, {
                preserveScroll: true,
                onError: (errors) => {
                    const msg = errors.submit ?? 'Không thể nộp báo cáo.';
                    toast.error(msg);
                },
            });
        },
        onError: () => toast.error('Không lưu được bản nháp. Kiểm tra dự án / task phát sinh rồi thử lại.'),
    });
};

// ---- Recall ---------------------------------------------------------------
const canRecall = computed(() => Boolean(props.report?.can?.recall));
const recalling  = ref(false);

const recall = async () => {
    if (!props.report || recalling.value) return;
    const reason = await dialog.prompt({
        title:       'Rút lại báo cáo?',
        message:     'Báo cáo sẽ trở về bản nháp để bạn chỉnh sửa và nộp lại. Người duyệt sẽ được thông báo. Chỉ rút lại được trong ngày làm việc hôm nay. Nêu lý do thu hồi (không bắt buộc):',
        placeholder: 'Ví dụ: bổ sung kết quả còn thiếu…',
        confirmText: 'Rút lại',
        cancelText:  'Huỷ',
    });
    if (reason === null) return;
    recalling.value = true;
    router.post(`/daily-reports/${props.report.id}/recall`, { reason }, {
        preserveScroll: true,
        onFinish: () => { recalling.value = false; },
    });
};

const lastSavedAt = ref(props.report ? new Date() : null);
let saveTimer     = null;
let snapshotTimer = null;

onMounted(() => {
    if (isEditing.value && editable.value) {
        saveTimer = setInterval(() => {
            if (form.isDirty && !form.processing) {
                form.put(`/daily-reports/${props.report.id}`, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: afterSaveSuccess,
                });
            }
        }, 30000);

        snapshotTimer = setInterval(() => {
            if (form.processing || document.visibilityState !== 'visible') return;
            router.reload({
                only: ['report'],
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    applyServerSpawnIds(props.report?.projects);
                },
            });
        }, 25000);
    }
});

onUnmounted(() => {
    if (saveTimer)     clearInterval(saveTimer);
    if (snapshotTimer) clearInterval(snapshotTimer);
});

const savedTimeLabel = computed(() =>
    lastSavedAt.value
        ? lastSavedAt.value.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
        : null,
);
</script>

<template>
  <Head title="Báo cáo hôm nay" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Báo cáo hôm nay"
        :subtitle="formattedToday"
        :badge="date(reportDate)"
        icon="report-today"
        icon-color="brand"
      />
    </template>

    <div class="w-full min-w-0 overflow-x-hidden">
      <!-- ── Trạng thái: đã nộp (không còn chỉnh sửa được) ── -->
      <div
        v-if="isEditing && !editable"
        class="card w-full overflow-hidden border border-brand/15 shadow-elevation-2"
      >
        <div class="bg-gradient-to-b from-brand/[0.06] via-white to-amber-50/40 px-6 py-10 text-center sm:px-10 sm:py-12">
          <div
            class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 ring-8 ring-emerald-50"
            aria-hidden="true"
          >
            <AppIcon
              name="done"
              :size="40"
              :stroke-width="2"
            />
          </div>

          <p class="text-xs font-bold uppercase tracking-widest text-brand/80">
            Hoàn tất nộp báo cáo
          </p>
          <h2 class="mt-2 font-display text-2xl font-semibold tracking-tight text-slate-800 sm:text-3xl">
            Bạn đã nộp báo cáo hôm nay
          </h2>
          <p class="mt-3 text-base font-medium text-slate-700">
            {{ formattedToday }}
          </p>

          <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
            <span
              class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-semibold"
              :class="statusVi.cls"
            >
              <AppIcon
                name="clock"
                :size="16"
              />
              {{ statusVi.label }}
            </span>
          </div>

          <p
            v-if="report?.title"
            class="mx-auto mt-6 max-w-lg text-left text-sm font-medium text-slate-700 sm:text-center"
          >
            <span class="text-slate-500">Tiêu đề:</span>
            {{ report.title }}
          </p>

          <p class="mx-auto mt-4 max-w-md text-base leading-relaxed text-slate-600">
            Cảm ơn bạn! Báo cáo đang chờ quản lý xem xét và đánh giá. Bạn có thể mở chi tiết để xem lại nội dung đã gửi.
          </p>

          <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
            <Link
              :href="`/daily-reports/${report.id}`"
              class="btn-primary inline-flex min-h-11 items-center justify-center gap-2 px-8 text-base font-semibold"
            >
              <AppIcon
                name="eye"
                :size="18"
              />
              Xem báo cáo
            </Link>
            <RecallButton
              v-if="canRecall"
              :recalling="recalling"
              label="Rút lại để chỉnh sửa"
              @recall="recall"
            />
            <Link
              href="/daily-reports"
              class="inline-flex min-h-11 items-center justify-center rounded-btn border border-slate-200 bg-white px-6 text-sm font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
            >
              Lịch sử báo cáo
            </Link>
          </div>

          <p
            v-if="canRecall"
            class="mt-4 text-xs text-slate-400"
          >
            Phát hiện sai sót? Bạn có thể rút lại báo cáo trong ngày hôm nay để chỉnh sửa mà không cần nhờ người duyệt trả lại.
          </p>
        </div>
      </div>

      <!-- ── Trình soạn thảo ── -->
      <div
        v-else
        class="w-full min-w-0"
      >
        <div class="space-y-4 md:space-y-5">
          <!-- Banner báo cáo bị trả lại -->
          <div
            v-if="report?.review_notes"
            class="card border-l-4 border-warning p-4 sm:p-5"
          >
            <p class="text-sm font-semibold text-slate-700">
              Báo cáo được trả lại
            </p>
            <p class="mt-1 text-sm text-slate-600">
              {{ report.review_notes }}
            </p>
          </div>

          <!-- ── Section 1: Thông tin chung ── -->
          <div
            id="section-info"
            class="card overflow-hidden"
          >
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-4 py-3 sm:px-6">
              <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                <div class="flex items-center gap-2">
                  <span
                    class="h-2 w-2 shrink-0 rounded-full bg-brand"
                    aria-hidden="true"
                  />
                  <h2 class="font-display text-sm font-semibold text-slate-800">
                    Thông tin chung
                  </h2>
                </div>
                <span class="flex items-center gap-1 text-xs text-slate-400">
                  <AppIcon
                    name="calendar"
                    :size="12"
                    aria-hidden="true"
                  />
                  {{ formattedToday }}
                </span>
              </div>
              <span
                class="inline-flex shrink-0 items-center gap-0.5 rounded-full px-2 py-0.5 text-[10px] font-semibold leading-none"
                :class="titleOk ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
              >
                <AppIcon
                  v-if="titleOk"
                  name="check"
                  :size="10"
                  :stroke-width="3"
                />
                {{ titleOk ? '1/1' : '0/1' }}
              </span>
            </div>

            <div class="grid gap-6 p-4 sm:p-6">
              <div>
                <label class="label flex items-center gap-1.5">
                  Tiêu đề báo cáo
                  <span class="text-danger">*</span>
                  <InfoTooltip text="Một dòng tóm tắt nội dung chính trong ngày. VD: &quot;Hoàn thiện màn hình đăng nhập &amp; sửa lỗi API&quot;." />
                </label>
                <input
                  v-model="form.title"
                  type="text"
                  class="input"
                  placeholder="VD: Tổng kết công việc ngày — Module báo cáo"
                >
                <p
                  v-if="form.errors.title"
                  class="mt-1 text-sm text-danger"
                >
                  {{ form.errors.title }}
                </p>
              </div>

              <div>
                <label class="label flex items-center gap-1.5">
                  Công việc trong ngày
                  <InfoTooltip text="Phần Dự án: chọn dự án và task đã làm. Phần Công việc thường xuyên: việc lặp lại không gắn dự án — vẫn được ghi trong báo cáo." />
                </label>
                <ProjectSelect
                  v-model="form.projects"
                  :options="projectOptions"
                  :task-status-snapshot="taskStatusSnapshot"
                />
              </div>
            </div>
          </div>

          <!-- ── Sections 2–5: Các trụ cột HORENSO ── -->
          <div
            v-for="p in pillars"
            :id="`section-${p.key}`"
            :key="p.key"
            class="card overflow-hidden"
          >
            <div
              class="flex items-center justify-between gap-3 border-b px-4 py-3 sm:px-6"
              :class="[pillarHeaderBg(p.key), pillarHeaderBorder(p.key)]"
            >
              <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                <div class="flex items-center gap-2">
                  <span
                    class="h-2 w-2 shrink-0 rounded-full"
                    :class="pillarDotClass(p.key)"
                    aria-hidden="true"
                  />
                  <h2 class="font-display text-sm font-semibold text-slate-800">
                    {{ p.title }}
                  </h2>
                </div>
                <span class="text-xs text-slate-400">{{ p.subtitle }}</span>
              </div>
              <span
                class="inline-flex shrink-0 items-center gap-0.5 rounded-full px-2 py-0.5 text-[10px] font-semibold leading-none"
                :class="pillarFillClass(p.key)"
              >
                <AppIcon
                  v-if="filledInPillar(p.key) === pillarFields(p.key).length"
                  name="check"
                  :size="10"
                  :stroke-width="3"
                />
                {{ filledInPillar(p.key) }}/{{ pillarFields(p.key).length }}
              </span>
            </div>

            <div class="grid gap-6 p-4 sm:p-6">
              <RichTextField
                v-for="f in pillarFields(p.key)"
                :key="f.key"
                v-model="form[f.key]"
                :label="f.label"
                :hint="f.hint"
                :tooltip="f.tooltip"
                :required="f.required"
                :placeholder="f.placeholder"
                :error="form.errors[f.key]"
              />
            </div>
          </div>

          <p
            v-if="form.errors.submit"
            class="text-sm text-danger"
          >
            {{ form.errors.submit }}
          </p>

          <!-- Thanh hành động (cuối trang) -->
          <div class="card min-w-0 overflow-hidden">
            <div
              class="h-1 bg-slate-100"
              role="progressbar"
              :aria-valuenow="progressPct"
              aria-valuemin="0"
              aria-valuemax="100"
              :aria-label="`Tiến độ hoàn thành ${progressPct} phần trăm`"
            >
              <div
                class="h-full bg-brand transition-all duration-300"
                :style="{ width: progressPct + '%' }"
              />
            </div>

            <div
              v-if="submitAttempted && !readyToSubmit"
              class="border-b border-amber-100 bg-amber-50 px-4 py-2 sm:px-6"
            >
              <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                <span class="shrink-0 text-xs font-medium text-amber-700">Còn thiếu:</span>
                <button
                  v-for="m in missingItems"
                  :key="m.label"
                  type="button"
                  class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-white px-2.5 py-1 text-xs font-medium text-amber-800 transition hover:border-amber-300 hover:bg-amber-50"
                  @click="scrollToSection(m.sectionKey)"
                >
                  {{ m.label }}
                  <AppIcon
                    name="chevron-right"
                    :size="11"
                  />
                </button>
              </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50/60 px-4 py-4 sm:flex-row sm:items-center sm:gap-3 sm:px-6">
              <!-- Trạng thái lưu + tiến độ -->
              <div class="flex min-w-0 items-center gap-2 text-xs text-slate-500">
                <span class="font-display font-semibold tabular-nums text-brand">{{ progressPct }}%</span>
                <span
                  v-if="isEditing"
                  class="flex items-center gap-1"
                >
                  <span class="text-slate-300">·</span>
                  <span
                    v-if="form.processing"
                    class="flex items-center gap-1"
                  >
                    <AppIcon
                      name="refresh"
                      :size="12"
                      class="animate-spin text-brand"
                    />
                    Đang lưu…
                  </span>
                  <span v-else-if="savedTimeLabel">Đã lưu {{ savedTimeLabel }}</span>
                  <span v-else>Chưa lưu lần nào hôm nay</span>
                </span>
              </div>

              <div class="flex w-full flex-col gap-2 sm:ml-auto sm:w-auto sm:flex-row sm:items-center">
                <button
                  type="button"
                  class="btn-ghost min-h-10 w-full justify-center gap-1.5 sm:w-auto"
                  @click="openGallery('builtin')"
                >
                  <AppIcon
                    name="template"
                    :size="14"
                  />
                  Thư viện mẫu
                </button>
                <button
                  type="button"
                  class="btn-ghost min-h-10 w-full sm:w-auto"
                  :disabled="form.processing"
                  @click="save"
                >
                  {{ form.processing ? 'Đang lưu…' : 'Lưu nháp' }}
                </button>
                <button
                  v-if="isEditing"
                  type="button"
                  class="btn-primary min-h-10 w-full sm:w-auto"
                  :disabled="form.processing || !readyToSubmit"
                  :title="readyToSubmit ? '' : 'Hãy điền đủ các mục bắt buộc trước khi nộp'"
                  @click="submit"
                >
                  Nộp duyệt
                </button>
              </div>
            </div>
          </div>

          <p class="text-center text-xs text-slate-400">
            <span v-if="isEditing">
              Bản nháp tự động lưu mỗi 30 giây.
              <span v-if="savedTimeLabel"> · Đã lưu lúc {{ savedTimeLabel }}</span>
            </span>
            <span v-else>Lưu nháp để bật tự động lưu và cho phép nộp duyệt.</span>
          </p>
        </div>
      </div>
    </div>

    <TemplateGallery
      v-model:open="galleryOpen"
      :builtins="builtinTemplates"
      :content="contentForSave"
      :storage-key="storageKey"
      :initial-tab="galleryTab"
      @apply="applyTemplate"
    />
  </AppLayout>
</template>
