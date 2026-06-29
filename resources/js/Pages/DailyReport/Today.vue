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

const taskStatusSnapshot = computed(
    () => props.report?.task_status_snapshot ?? [],
);

// Auto-generated title prefix for a new report: "Báo cáo ngày DD/MM/YYYY - ".
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

// Each missing item knows which tab to jump to — chips below act as shortcuts.
const tabIndexOfPillar = (pillarKey) =>
    Math.max(0, tabs.value.findIndex((t) => t.key === pillarKey));
const missingItems = computed(() => {
    const items = [];
    if (!titleOk.value) items.push({ label: 'Tiêu đề', tab: 0 });
    fields
        .filter((f) => f.required && !isFilled(f.key))
        .forEach((f) => items.push({ label: f.label, tab: tabIndexOfPillar(f.pillar) }));
    return items;
});
const missingLabel = computed(() => missingItems.value.map((m) => m.label).join(', '));
const goToTab = (i) => { activeTab.value = i; };

// ---- Auto-fill chosen tasks into the "Tiến độ thực hiện" field ------------
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

// Build an HTML list (grouped by project) of selected tasks matching `predicate`.
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

// "Cần làm" (todo) tasks → Mục tiêu hôm nay; mọi task khác → Tiến độ thực hiện.
const isTodo = (st) => st === 'todo';
const buildGoalsHtml = () => buildTaskHtml(isTodo, false);
const buildProgressHtml = () => buildTaskHtml((st) => !isTodo(st), true);

// Keep these fields in sync with the chosen tasks, but never overwrite text
// typed manually — only fill when empty or still matching our last fill.
const lastAutoGoals = ref(buildGoalsHtml());
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

const activeTab = ref(0);

// Tab 0 = general info, tabs 1..N = Horenso pillars.
const tabs = computed(() => [
    {
        key: 'info', title: 'Thông tin chung', pillar: null,
        jp: '基本', romaji: 'Kihon',
        desc: 'Tiêu đề báo cáo và các dự án / task bạn đã làm việc hôm nay.',
        filled: titleOk.value ? 1 : 0, total: 1,
        hasMissingRequired: !titleOk.value,
    },
    ...pillars.map((p) => ({
        key: p.key, title: p.title, pillar: p,
        jp: p.jp, romaji: p.romaji, desc: p.desc,
        filled: filledInPillar(p.key), total: pillarFields(p.key).length,
        hasMissingRequired: pillarFields(p.key).some((f) => f.required && !isFilled(f.key)),
    })),
]);
const activeTabMeta = computed(() => tabs.value[activeTab.value]);

const formattedToday = computed(() => dateLongVi(reportDate.value));

const statusVi = computed(() => {
    const map = {
        draft: { label: 'Bản nháp', cls: 'bg-slate-100 text-slate-600' },
        submitted: { label: 'Chờ duyệt', cls: 'bg-amber-100 text-amber-700' },
        reviewed: { label: 'Đã duyệt', cls: 'bg-emerald-100 text-emerald-700' },
    };
    return map[props.report?.status] ?? { label: 'Chưa lưu', cls: 'bg-slate-100 text-slate-500' };
});

// ---- Templates ------------------------------------------------------------
const galleryOpen = ref(false);
const galleryTab = ref('builtin');
const storageKey = computed(() => `va-qlda.report-templates.${page.props.auth?.user?.id ?? 'guest'}`);
const contentForSave = computed(() => Object.fromEntries(contentKeys.map((k) => [k, form[k]])));

const openGallery = (tab = 'builtin') => {
    galleryTab.value = tab;
    galleryOpen.value = true;
};

const applyTemplate = async (map) => {
    const willOverwrite = contentKeys.some((k) => k in map && isFilled(k) && map[k] !== form[k]);
    if (willOverwrite) {
        const ok = await dialog.confirm({
            title: 'Áp dụng mẫu?',
            message: 'Thao tác này sẽ ghi đè nội dung đang có ở một số mục. Bạn có chắc chắn?',
            confirmText: 'Áp dụng',
            cancelText: 'Huỷ',
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

// ---- Recall (pull a just-submitted report back to draft, same day only) ---
const canRecall = computed(() => Boolean(props.report?.can?.recall));
const recalling = ref(false);

const recall = async () => {
    if (!props.report || recalling.value) return;
    const reason = await dialog.prompt({
        title: 'Rút lại báo cáo?',
        message: 'Báo cáo sẽ trở về bản nháp để bạn chỉnh sửa và nộp lại. Người duyệt sẽ được thông báo. Chỉ rút lại được trong ngày làm việc hôm nay. Nêu lý do thu hồi (không bắt buộc):',
        placeholder: 'Ví dụ: bổ sung kết quả còn thiếu…',
        confirmText: 'Rút lại',
        cancelText: 'Huỷ',
    });
    if (reason === null) return; // cancelled
    recalling.value = true;
    router.post(`/daily-reports/${props.report.id}/recall`, { reason }, {
        preserveScroll: true,
        onFinish: () => { recalling.value = false; },
    });
};

const lastSavedAt = ref(props.report ? new Date() : null);
let saveTimer = null;
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
    if (saveTimer) clearInterval(saveTimer);
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

    <div class="w-full min-w-0">
      <!-- Already submitted today -->
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

      <div
        v-else
        class="w-full space-y-5"
      >
        <!-- Action bar (progress + save/submit) -->
        <div class="card sticky top-0 z-10 flex min-w-0 flex-wrap items-center justify-between gap-3 p-4">
          <div class="min-w-0 w-full flex-1 sm:min-w-[12rem] sm:w-auto">
            <div class="mb-1 flex items-center justify-between text-xs">
              <span class="font-medium text-slate-600">Tiến độ hoàn thành</span>
              <span class="font-semibold text-brand">{{ progressPct }}%</span>
            </div>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
              <div
                class="h-full rounded-full bg-brand transition-all duration-300"
                :style="{ width: progressPct + '%' }"
              />
            </div>
            <div class="mt-1 text-xs">
              <p
                v-if="readyToSubmit"
                class="flex items-center gap-1 text-emerald-600"
              >
                <AppIcon
                  name="check"
                  :size="13"
                />
                Đã đủ điều kiện nộp duyệt.
              </p>
              <div
                v-else
                class="flex flex-wrap items-center gap-1.5"
              >
                <span class="text-amber-600">Còn thiếu:</span>
                <button
                  v-for="m in missingItems"
                  :key="m.label"
                  type="button"
                  class="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 font-medium text-amber-700 transition hover:border-amber-300 hover:bg-amber-100"
                  @click="goToTab(m.tab)"
                >
                  {{ m.label }}
                  <AppIcon
                    name="chevron-right"
                    :size="11"
                  />
                </button>
              </div>
            </div>
          </div>
          <div class="flex w-full min-w-0 flex-wrap items-center justify-end gap-2 sm:w-auto">
            <span
              v-if="isEditing"
              class="hidden items-center gap-1 text-xs text-slate-400 sm:inline-flex"
            >
              <AppIcon
                :name="form.processing ? 'refresh' : 'check'"
                :size="13"
                :class="form.processing ? 'animate-spin text-brand' : 'text-emerald-500'"
              />
              <span v-if="form.processing">Đang lưu…</span>
              <span v-else-if="savedTimeLabel">Đã lưu {{ savedTimeLabel }}</span>
            </span>
            <button
              type="button"
              class="btn-ghost gap-1.5"
              @click="openGallery('builtin')"
            >
              <AppIcon
                name="template"
                :size="16"
              /> Thư viện mẫu
            </button>
            <button
              type="button"
              class="btn-ghost"
              :disabled="form.processing"
              @click="save"
            >
              {{ form.processing ? 'Đang lưu…' : 'Lưu nháp' }}
            </button>
            <button
              v-if="isEditing"
              type="button"
              class="btn-primary"
              :disabled="form.processing || !readyToSubmit"
              :title="readyToSubmit ? '' : 'Hãy điền đủ các mục bắt buộc trước khi nộp'"
              @click="submit"
            >
              Nộp duyệt
            </button>
          </div>
        </div>

        <!-- Rejected feedback -->
        <div
          v-if="report?.review_notes"
          class="card border-l-4 border-warning p-4"
        >
          <p class="text-sm font-semibold text-slate-700">
            Báo cáo được trả lại
          </p>
          <p class="mt-1 text-sm text-slate-600">
            {{ report.review_notes }}
          </p>
        </div>

        <!-- All sections as tabs -->
        <div class="card min-w-0 overflow-hidden">
          <!-- Tab bar -->
          <div
            class="grid grid-cols-2 border-b border-slate-200 sm:grid-cols-3 lg:grid-cols-5"
            role="tablist"
            aria-label="Các phần báo cáo"
          >
            <button
              v-for="(t, i) in tabs"
              :key="t.key"
              type="button"
              role="tab"
              :aria-selected="activeTab === i"
              class="border-b-2 px-2 py-3 text-left text-sm font-medium transition sm:px-3 sm:text-center"
              :class="activeTab === i
                ? 'border-brand text-brand'
                : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
              @click="activeTab = i"
            >
              <span class="flex flex-wrap items-center justify-center gap-x-2 gap-y-1">
                <span class="min-w-0">{{ t.title }}</span>
                <span
                  class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                  :class="t.filled === t.total
                    ? 'bg-emerald-100 text-emerald-700'
                    : t.hasMissingRequired
                      ? 'bg-amber-100 text-amber-700'
                      : 'bg-slate-100 text-slate-500'"
                >
                  <AppIcon
                    v-if="t.filled === t.total"
                    name="check"
                    :size="10"
                    :stroke-width="3"
                  />
                  {{ t.filled }}/{{ t.total }}
                </span>
              </span>
            </button>
          </div>

          <!-- Active tab -->
          <div class="min-w-0 p-4 sm:p-6">
            <div class="mb-5 flex flex-col gap-2 rounded-card bg-slate-50 p-3 sm:flex-row sm:items-start sm:justify-between sm:gap-3">
              <div class="min-w-0 space-y-1">
                <span class="font-mono text-xs text-slate-400">{{ activeTabMeta.jp }} · {{ activeTabMeta.romaji }}</span>
                <p class="text-xs leading-relaxed text-slate-500">
                  {{ activeTabMeta.desc }}
                </p>
              </div>
              <span
                class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="statusVi.cls"
              >
                {{ statusVi.label }}
              </span>
            </div>

            <!-- Tab: general info -->
            <div
              v-if="activeTabMeta.key === 'info'"
              class="grid min-w-0 gap-6"
            >
              <div>
                <label class="label flex items-center gap-1.5">
                  Ngày báo cáo
                  <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-400">Chỉ đọc</span>
                </label>
                <div
                  class="flex flex-wrap items-center gap-2 rounded-card border border-dashed border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600"
                >
                  <AppIcon
                    name="calendar"
                    :size="16"
                    class="shrink-0 text-brand"
                  />
                  <span class="font-medium text-slate-700">{{ formattedToday }}</span>
                  <span class="text-xs text-slate-400">(theo giờ làm việc VN)</span>
                </div>
              </div>

              <div>
                <label class="label flex items-center gap-1.5">
                  Tiêu đề báo cáo <span class="text-danger">*</span>
                  <InfoTooltip text="Một dòng tóm tắt nội dung chính trong ngày. VD: “Hoàn thiện màn hình đăng nhập & sửa lỗi API”." />
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

            <!-- Tab: a Horenso pillar -->
            <div
              v-else
              class="grid min-w-0 gap-6"
            >
              <RichTextField
                v-for="f in pillarFields(activeTabMeta.key)"
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

            <!-- Tab nav -->
            <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
              <button
                type="button"
                class="btn-ghost"
                :disabled="activeTab === 0"
                @click="activeTab--"
              >
                ← Trước
              </button>
              <span class="text-xs text-slate-400">Bước {{ activeTab + 1 }}/{{ tabs.length }}</span>
              <button
                type="button"
                class="btn-ghost"
                :disabled="activeTab === tabs.length - 1"
                @click="activeTab++"
              >
                Tiếp →
              </button>
            </div>
          </div>
        </div>

        <p
          v-if="form.errors.submit"
          class="text-sm text-danger"
        >
          {{ form.errors.submit }}
        </p>
        <p class="text-center text-xs text-slate-400">
          <span v-if="isEditing">
            Bản nháp tự động lưu mỗi 30 giây.
            <span v-if="savedTimeLabel"> · Đã lưu lúc {{ savedTimeLabel }}</span>
          </span>
          <span v-else>Lưu nháp để bật tự động lưu và cho phép nộp duyệt.</span>
        </p>
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
