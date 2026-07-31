<script setup>
import { computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import WorkspaceConfigItemGrid from '@/modules/workspace-config/components/WorkspaceConfigItemGrid.vue';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    workspace: { type: Object, required: true },
    modules: { type: Array, default: () => [] },
    checklist: {
        type: Object,
        default: () => ({ items: [], done: 0, total: 0 }),
    },
    viewer: {
        type: Object,
        default: () => ({ can_manage: false, own_department_code: null }),
    },
});

const dialog = useDialog();

const notesForm = useForm({
    notes: props.workspace.notes ?? '',
});

watch(
    () => props.workspace.notes,
    (v) => {
        notesForm.notes = v ?? '';
    },
);

const statusClass = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80',
    draft: 'bg-amber-50 text-amber-800 ring-amber-200/80',
    missing: 'bg-slate-100 text-slate-600 ring-slate-200/80',
    archived: 'bg-rose-50 text-rose-700 ring-rose-200/80',
};

const readinessClass = {
    ready: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80',
    partial: 'bg-sky-50 text-sky-700 ring-sky-200/80',
    empty: 'bg-slate-100 text-slate-500 ring-slate-200/80',
};

const readiness = computed(() => props.workspace.readiness ?? {
    key: 'empty',
    label: 'Chưa có nội dung',
    percent: 0,
    configured: 0,
    total: 0,
});

const checklistItems = computed(() => props.checklist?.items ?? []);
const checklistDone = computed(() => props.checklist?.done ?? 0);
const checklistTotal = computed(() => props.checklist?.total ?? 0);

const subtitle = computed(() => {
    const code = props.workspace.department_code;
    const parts = [
        `Mã ${code}`,
        `Tiêu chí PB: ${props.workspace.criteria_count}`,
        `Tiêu chí chung: ${props.workspace.criteria_general}`,
    ];
    if (props.workspace.source_label) {
        parts.push(props.workspace.source_label);
    }
    return parts.join(' · ');
});

const liveCount = computed(() => props.modules.filter((m) => m.status === 'live').length);
const plannedCount = computed(() => props.modules.filter((m) => m.status === 'planned').length);
const canUpdate = computed(() => props.viewer.can_manage && props.workspace.profile_id);

function ensureWorkspace() {
    router.post(
        `/workspace-config/w/${encodeURIComponent(props.workspace.department_code)}/ensure`,
        {},
        { preserveScroll: true },
    );
}

function saveNotes() {
    if (!canUpdate.value) return;
    notesForm.patch(
        `/workspace-config/w/${encodeURIComponent(props.workspace.department_code)}`,
        { preserveScroll: true },
    );
}

async function archiveWorkspace() {
    if (!canUpdate.value) return;
    const ok = await dialog.confirm({
        title: 'Lưu trữ workspace?',
        message: `Workspace «${props.workspace.department_name}» sẽ được lưu trữ và ẩn khỏi hub mặc định.`,
        confirmText: 'Lưu trữ',
        cancelText: 'Huỷ',
        tone: 'danger',
    });
    if (!ok) return;
    router.patch(
        `/workspace-config/w/${encodeURIComponent(props.workspace.department_code)}`,
        { status: 'archived' },
        { preserveScroll: true },
    );
}

function restoreWorkspace() {
    if (!canUpdate.value) return;
    router.patch(
        `/workspace-config/w/${encodeURIComponent(props.workspace.department_code)}`,
        { status: 'active' },
        { preserveScroll: true },
    );
}
</script>

<template>
  <Head :title="`Workspace · ${workspace.department_name}`" />

  <AppLayout>
    <template #header>
      <PageHeader
        :title="workspace.department_name"
        :subtitle="subtitle"
        icon="department"
        icon-color="brand"
        back-href="/workspace-config"
      >
        <span
          class="inline-flex items-center rounded-md px-2 py-1 text-[10px] font-semibold uppercase tracking-wide ring-1"
          :class="statusClass[workspace.status] ?? statusClass.missing"
        >
          {{ workspace.status_label }}
        </span>
        <span
          class="inline-flex items-center rounded-md px-2 py-1 text-[10px] font-semibold tracking-wide ring-1"
          :class="readinessClass[readiness.key] ?? readinessClass.empty"
        >
          {{ readiness.label }} · {{ readiness.percent }}%
        </span>
        <button
          v-if="viewer.can_manage && workspace.status === 'missing'"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="ensureWorkspace"
        >
          <AppIcon
            name="plus"
            :size="15"
          />
          Kích hoạt workspace
        </button>
        <button
          v-if="canUpdate && workspace.status === 'archived'"
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="restoreWorkspace"
        >
          Khôi phục
        </button>
        <button
          v-if="canUpdate && workspace.status !== 'archived' && workspace.status !== 'missing'"
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs text-rose-700"
          @click="archiveWorkspace"
        >
          Lưu trữ
        </button>
        <Link
          :href="workspace.evaluation_href || `/workspace-config/evaluation?department_code=${encodeURIComponent(workspace.department_code)}&scope=department`"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="award"
            :size="15"
          />
          Tiêu chí đánh giá
          <span
            v-if="workspace.criteria_count"
            class="tabular-nums"
          >({{ workspace.criteria_count }})</span>
        </Link>
      </PageHeader>
    </template>

    <section
      class="mb-5 overflow-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white shadow-sm"
      aria-label="Tổng quan sẵn sàng workspace"
    >
      <div class="grid grid-cols-2 gap-px bg-slate-100/80 sm:grid-cols-4">
        <div class="bg-white px-4 py-3.5">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Sẵn sàng
          </p>
          <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
            {{ readiness.percent }}%
          </p>
          <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
              class="h-full rounded-full bg-brand transition-all"
              :style="{ width: `${readiness.percent}%` }"
            />
          </div>
        </div>
        <div class="bg-white px-4 py-3.5">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Tiêu chí phòng ban
          </p>
          <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
            {{ workspace.criteria_count }}
          </p>
          <p class="mt-1 text-[11px] text-slate-500">
            {{ workspace.has_criteria ? 'Đã có bộ tiêu chí' : 'Chưa có tiêu chí riêng' }}
          </p>
        </div>
        <div class="bg-white px-4 py-3.5">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Module live
          </p>
          <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
            {{ readiness.configured }}/{{ liveCount }}
          </p>
          <p class="mt-1 text-[11px] text-slate-500">
            Đã cấu hình / đang dùng
          </p>
        </div>
        <div class="bg-white px-4 py-3.5">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Checklist
          </p>
          <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
            {{ checklistDone }}/{{ checklistTotal }}
          </p>
          <p class="mt-1 text-[11px] text-slate-500">
            {{ plannedCount }} mục sắp ra mắt
          </p>
        </div>
      </div>
    </section>

    <div class="mb-5 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
      <section
        class="kpi-strip relative overflow-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white shadow-sm"
        aria-label="Mục cấu hình workspace phòng ban"
      >
        <header class="relative border-b border-slate-100/80 px-5 py-4">
          <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
            Module
          </p>
          <h2 class="mt-1 font-display text-base font-semibold text-slate-800">
            Cấu hình nghiệp vụ
          </h2>
          <p class="mt-1 text-sm text-slate-500">
            Số trên thẻ phản ánh dữ liệu thật — không phụ thuộc việc đã kích hoạt profile hay chưa.
          </p>
        </header>
        <div class="relative p-4 md:p-5">
          <WorkspaceConfigItemGrid :items="modules" />
          <p
            v-if="!modules.length"
            class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 ring-1 ring-slate-200/70"
          >
            Chưa có mục cấu hình nào bạn được phép xem trong workspace này.
          </p>
        </div>
      </section>

      <aside class="space-y-5">
        <section class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm">
          <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
            Onboard
          </p>
          <h2 class="mt-1 font-display text-sm font-semibold text-slate-800">
            Checklist thiết lập
          </h2>
          <ul class="mt-3 space-y-2">
            <li
              v-for="step in checklistItems"
              :key="step.key"
              class="flex items-start gap-2 text-sm"
              :class="step.planned ? 'text-slate-400' : 'text-slate-700'"
            >
              <AppIcon
                :name="step.done ? 'done' : (step.planned ? 'calendar' : 'system-config')"
                :size="14"
                class="mt-0.5 shrink-0"
                :class="step.done ? 'text-emerald-600' : 'text-slate-400'"
              />
              <div class="min-w-0">
                <p :class="step.done ? 'font-medium' : ''">
                  {{ step.label }}
                </p>
                <p class="text-[11px] text-slate-400">
                  {{ step.done_hint }}
                </p>
              </div>
            </li>
          </ul>
        </section>

        <section
          v-if="viewer.can_manage"
          class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm"
        >
          <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
            Ghi chú
          </p>
          <h2 class="mt-1 font-display text-sm font-semibold text-slate-800">
            Ghi chú nội bộ
          </h2>
          <textarea
            v-model="notesForm.notes"
            rows="4"
            class="input mt-3 w-full text-sm"
            :disabled="!canUpdate || notesForm.processing"
            placeholder="Ghi chú cấu hình, lưu ý onboard…"
          />
          <p
            v-if="notesForm.errors.notes"
            class="mt-1 text-xs text-rose-600"
          >
            {{ notesForm.errors.notes }}
          </p>
          <button
            v-if="canUpdate"
            type="button"
            class="btn-primary mt-3 inline-flex h-9 items-center gap-1.5 px-3 text-xs"
            :disabled="notesForm.processing"
            @click="saveNotes"
          >
            <AppIcon
              name="documents"
              :size="15"
            />
            Lưu ghi chú
          </button>
          <p
            v-else
            class="mt-2 text-[11px] text-slate-400"
          >
            Kích hoạt workspace trước để lưu ghi chú.
          </p>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
