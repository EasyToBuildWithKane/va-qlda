<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import RichTextField from '@/Components/DailyReport/RichTextField.vue';
import ProjectSelect from '@/Components/DailyReport/ProjectSelect.vue';
import TemplateGallery from '@/Components/DailyReport/TemplateGallery.vue';
import InfoTooltip from '@/Components/DailyReport/InfoTooltip.vue';
import { pillars, fields, builtinTemplates } from '@/Components/DailyReport/reportConfig';
import { useDialog } from '@/composables/useDialog';

const dialog = useDialog();

const props = defineProps({
    report: { type: Object, default: null },
    today: { type: String, required: true },
    projectOptions: { type: Array, default: () => [] },
});

const page = usePage();
const isEditing = computed(() => props.report !== null);
const editable = computed(() => !props.report || props.report.status === 'draft');

// Auto-generated title prefix for a new report: "Báo cáo ngày DD/MM/YYYY - ".
const titlePrefix = `Báo cáo ngày ${new Date(props.today + 'T00:00:00').toLocaleDateString('vi-VN')} - `;

const form = useForm({
    date: props.today,
    title: props.report?.title ?? titlePrefix,
    projects: props.report?.projects ?? [],
    goals_today: props.report?.goals_today ?? '',
    progress_update: props.report?.progress_update ?? '',
    results_impact: props.report?.results_impact ?? '',
    highlights: props.report?.highlights ?? '',
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
const missingLabel = computed(() =>
    [!titleOk.value ? 'Tiêu đề' : null, ...missingRequired.value].filter(Boolean).join(', '),
);

// ---- Auto-fill chosen tasks into the "Tiến độ thực hiện" field ------------
const taskStatusLabels = {
    todo: 'Cần làm', in_progress: 'Đang làm', in_review: 'Đang review',
    done: 'Hoàn thành', blocked: 'Bị chặn',
};
const escapeHtml = (s) =>
    String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const taskStatusOf = (projectId, taskId) =>
    props.projectOptions.find((o) => o.id === projectId)?.tasks
        ?.find((t) => t.id === taskId)?.status ?? null;

// Build an HTML list (grouped by project) of selected tasks matching `predicate`.
const buildTaskHtml = (predicate, showStatus = true) => {
    const blocks = [];
    for (const p of form.projects) {
        const tasks = (p.tasks || []).filter((t) => predicate(taskStatusOf(p.id, t.id)));
        if (!tasks.length) continue;
        const items = tasks.map((t) => {
            const st = taskStatusOf(p.id, t.id);
            const tag = showStatus && st ? ` — ${taskStatusLabels[st] || st}` : '';
            return `<li>${escapeHtml(t.title)}${tag}</li>`;
        }).join('');
        blocks.push(`<p><strong>${escapeHtml(p.name)}</strong></p><ul>${items}</ul>`);
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
    },
    ...pillars.map((p) => ({
        key: p.key, title: p.title, pillar: p,
        jp: p.jp, romaji: p.romaji, desc: p.desc,
        filled: filledInPillar(p.key), total: pillarFields(p.key).length,
    })),
]);
const activeTabMeta = computed(() => tabs.value[activeTab.value]);

const formattedToday = computed(() =>
    new Date(props.today + 'T00:00:00').toLocaleDateString('vi-VN', {
        weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric',
    }),
);

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
const save = () => {
    if (isEditing.value) {
        form.put(`/daily-reports/${props.report.id}`, { preserveScroll: true });
    } else {
        form.post('/daily-reports');
    }
};

const submit = () => {
    if (!isEditing.value) return;
    form.put(`/daily-reports/${props.report.id}`, {
        preserveScroll: true,
        onSuccess: () => router.post(`/daily-reports/${props.report.id}/submit`),
    });
};

const lastSavedAt = ref(props.report ? new Date() : null);
let timer = null;
onMounted(() => {
    if (isEditing.value && editable.value) {
        timer = setInterval(() => {
            if (form.isDirty && !form.processing) {
                form.put(`/daily-reports/${props.report.id}`, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => (lastSavedAt.value = new Date()),
                });
            }
        }, 30000);
    }
});
onUnmounted(() => timer && clearInterval(timer));

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
                icon="report-today"
                icon-color="brand"
            />
        </template>

        <!-- Already submitted today -->
        <div v-if="isEditing && !editable" class="card mx-auto max-w-xl p-8 text-center">
            <h2 class="font-display text-lg font-semibold text-slate-800">Bạn đã nộp báo cáo hôm nay</h2>
            <p class="mt-1 text-sm text-slate-500">Cảm ơn bạn! Báo cáo đang chờ quản lý xem xét và đánh giá.</p>
            <Link :href="`/daily-reports/${report.id}`" class="btn-primary mt-5">Xem báo cáo</Link>
        </div>

        <div v-else class="w-full space-y-5">
            <!-- Action bar (progress + save/submit) -->
            <div class="card sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="min-w-[14rem] flex-1">
                    <div class="mb-1 flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-600">Tiến độ hoàn thành</span>
                        <span class="font-semibold text-brand">{{ progressPct }}%</span>
                    </div>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-brand transition-all duration-300" :style="{ width: progressPct + '%' }"></div>
                    </div>
                    <p class="mt-1 text-xs" :class="readyToSubmit ? 'text-emerald-600' : 'text-amber-600'">
                        <span v-if="readyToSubmit">Đã đủ điều kiện nộp duyệt.</span>
                        <span v-else>Còn thiếu: {{ missingLabel }}</span>
                    </p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" class="btn-ghost gap-1.5" @click="openGallery('builtin')">
                        <AppIcon name="template" :size="16" /> Thư viện mẫu
                    </button>
                    <button type="button" class="btn-ghost" :disabled="form.processing" @click="save">
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
            <div v-if="report?.review_notes" class="card border-l-4 border-warning p-4">
                <p class="text-sm font-semibold text-slate-700">Báo cáo được trả lại</p>
                <p class="mt-1 text-sm text-slate-600">{{ report.review_notes }}</p>
            </div>

            <!-- All sections as tabs -->
            <div class="card overflow-hidden">
                <!-- Tab bar -->
                <div class="flex flex-wrap border-b border-slate-200">
                    <button
                        v-for="(t, i) in tabs"
                        :key="t.key"
                        type="button"
                        class="flex-1 border-b-2 px-3 py-3 text-sm font-medium transition"
                        :class="activeTab === i
                            ? 'border-brand text-brand'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                        @click="activeTab = i"
                    >
                        <span class="flex items-center justify-center gap-2">
                            {{ t.title }}
                            <span
                                class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                                :class="t.filled === t.total
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-slate-100 text-slate-500'"
                            >{{ t.filled }}/{{ t.total }}</span>
                        </span>
                    </button>
                </div>

                <!-- Active tab -->
                <div class="p-6">
                    <div class="mb-5 flex items-start justify-between gap-3 rounded-card bg-slate-50 p-3">
                        <div class="flex items-start gap-2">
                            <span class="font-mono text-xs text-slate-400">{{ activeTabMeta.jp }} · {{ activeTabMeta.romaji }}</span>
                            <p class="text-xs text-slate-500">{{ activeTabMeta.desc }}</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusVi.cls">
                            {{ statusVi.label }}
                        </span>
                    </div>

                    <!-- Tab: general info -->
                    <div v-if="activeTabMeta.key === 'info'" class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <label class="label flex items-center gap-1.5">
                                Tiêu đề báo cáo <span class="text-danger">*</span>
                                <InfoTooltip text="Một dòng tóm tắt nội dung chính trong ngày. VD: “Hoàn thiện màn hình đăng nhập & sửa lỗi API”." />
                            </label>
                            <input v-model="form.title" type="text" class="input" placeholder="VD: Tổng kết công việc ngày — Module báo cáo" />
                            <p v-if="form.errors.title" class="mt-1 text-sm text-danger">{{ form.errors.title }}</p>
                        </div>

                        <div>
                            <label class="label flex items-center gap-1.5">
                                Dự án &amp; công việc trong ngày
                                <InfoTooltip text="Chọn (các) dự án bạn đã làm việc hôm nay. Sau khi chọn dự án, bạn có thể thêm các task của dự án đó kèm theo." />
                            </label>
                            <ProjectSelect v-model="form.projects" :options="projectOptions" />
                        </div>
                    </div>

                    <!-- Tab: a Horenso pillar -->
                    <div v-else class="grid gap-5" :class="pillarFields(activeTabMeta.key).length > 2 ? 'xl:grid-cols-2' : ''">
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
                        <button type="button" class="btn-ghost" :disabled="activeTab === 0" @click="activeTab--">← Trước</button>
                        <span class="text-xs text-slate-400">Bước {{ activeTab + 1 }}/{{ tabs.length }}</span>
                        <button type="button" class="btn-ghost" :disabled="activeTab === tabs.length - 1" @click="activeTab++">Tiếp →</button>
                    </div>
                </div>
            </div>

            <p v-if="form.errors.submit" class="text-sm text-danger">{{ form.errors.submit }}</p>
            <p class="text-center text-xs text-slate-400">
                <span v-if="isEditing">
                    Bản nháp tự động lưu mỗi 30 giây.
                    <span v-if="savedTimeLabel"> · Đã lưu lúc {{ savedTimeLabel }}</span>
                </span>
                <span v-else>Lưu nháp để bật tự động lưu và cho phép nộp duyệt.</span>
            </p>
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
