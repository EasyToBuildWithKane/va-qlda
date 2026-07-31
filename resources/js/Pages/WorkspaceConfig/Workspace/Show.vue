<script setup>
import { computed, toRef, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useClientPagination } from '@/shared/composables/useClientPagination';
import WorkspaceConfigItemGrid from '@/modules/workspace-config/components/WorkspaceConfigItemGrid.vue';

const MODULES_PAGE_THRESHOLD = 5;

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

const notesForm = useForm({
    notes: props.workspace.notes ?? '',
});

watch(
    () => props.workspace.notes,
    (v) => {
        notesForm.notes = v ?? '';
    },
);

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

const liveCount = computed(() => props.modules.filter((m) => m.status === 'live').length);
const canUpdate = computed(() => props.viewer.can_manage && props.workspace.profile_id);

const modulesAll = toRef(props, 'modules');
const {
    paginatedItems: modulesPage,
    meta: modulesMeta,
    perPage: modulesPerPage,
    setPerPage: setModulesPerPage,
    goToPage: goToModulesPage,
    PER_PAGE_OPTIONS: modulesPerPageOptions,
} = useClientPagination(
    modulesAll,
    'va-workspace.workspace-config.modules.perPage',
    MODULES_PAGE_THRESHOLD,
);

const showModulesPagination = computed(() => modulesMeta.value.total > MODULES_PAGE_THRESHOLD);

const headerSubtitle = computed(() => {
    const parts = [
        props.workspace.source_label || null,
        readiness.value.label,
    ].filter(Boolean);
    return parts.join(' · ');
});

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
</script>

<template>
  <Head :title="`Workspace · ${workspace.department_name}`" />

  <AppLayout>
    <template #header>
      <PageHeader
        :title="workspace.department_name"
        :subtitle="headerSubtitle"
        :badge="workspace.department_code"
        icon="department"
        icon-color="brand"
        back-href="/workspace-config"
      >
        <button
          v-if="viewer.can_manage && workspace.status === 'missing'"
          type="button"
          class="btn-primary inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-xs"
          @click="ensureWorkspace"
        >
          <AppIcon
            name="plus"
            :size="15"
          />
          Kích hoạt
        </button>
        <Link
          :href="workspace.evaluation_href || `/workspace-config/evaluation?department_code=${encodeURIComponent(workspace.department_code)}&scope=department`"
          class="btn-ghost inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="award"
            :size="15"
          />
          Tiêu chí
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
      <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Tổng quan
        </p>
        <h2 class="mt-0.5 font-display text-sm font-semibold text-slate-800">
          Sẵn sàng cấu hình phòng ban
        </h2>
      </div>
      <div class="grid grid-cols-2 gap-px bg-slate-100/80 sm:grid-cols-4">
        <div class="bg-white px-4 py-3.5 sm:px-5">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Sẵn sàng
          </p>
          <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
            {{ readiness.percent }}%
          </p>
          <p class="mt-0.5 text-[11px] leading-snug text-slate-500">
            {{ readiness.label }}
          </p>
          <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
              class="h-full rounded-full bg-brand transition-all"
              :style="{ width: `${readiness.percent}%` }"
            />
          </div>
        </div>
        <div class="bg-white px-4 py-3.5 sm:px-5">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Tiêu chí PB
          </p>
          <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
            {{ workspace.criteria_count }}
          </p>
          <p class="mt-0.5 text-[11px] leading-snug text-slate-500">
            {{ workspace.criteria_general ? `${workspace.criteria_general} tiêu chí chung` : 'Theo phòng ban' }}
          </p>
        </div>
        <div class="bg-white px-4 py-3.5 sm:px-5">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Module live
          </p>
          <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
            {{ readiness.configured }}/{{ liveCount }}
          </p>
          <p class="mt-0.5 text-[11px] leading-snug text-slate-500">
            Đã cấu hình / module live
          </p>
        </div>
        <div class="bg-white px-4 py-3.5 sm:px-5">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Checklist
          </p>
          <p class="mt-1 font-display text-2xl font-semibold tabular-nums text-slate-800">
            {{ checklistDone }}/{{ checklistTotal }}
          </p>
          <p class="mt-0.5 text-[11px] leading-snug text-slate-500">
            Bước thiết lập hoàn tất
          </p>
        </div>
      </div>
    </section>

    <div class="mb-5 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
      <section
        class="rounded-card border border-slate-200/80 bg-white shadow-sm"
        aria-label="Mục cấu hình workspace phòng ban"
      >
        <header class="flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 px-4 py-3.5 sm:px-5">
          <div class="min-w-0">
            <h2 class="font-display text-sm font-semibold text-slate-800">
              Cấu hình nghiệp vụ
            </h2>
            <p class="mt-0.5 text-[12px] leading-snug text-slate-500">
              Mở từng mục để cấu hình theo phòng ban {{ workspace.department_code }}.
            </p>
          </div>
          <span
            v-if="modules.length"
            class="shrink-0 rounded-md bg-slate-50 px-2 py-0.5 text-[11px] tabular-nums text-slate-500 ring-1 ring-slate-200/70"
          >{{ modules.length }} module</span>
        </header>
        <div class="p-4 sm:p-5">
          <WorkspaceConfigItemGrid
            v-if="modules.length"
            :items="modulesPage"
          />
          <p
            v-else
            class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm leading-relaxed text-slate-500 ring-1 ring-slate-200/70"
          >
            Chưa có mục cấu hình nào bạn được phép xem trong workspace này.
          </p>
        </div>
        <DatagridPaginationFooter
          v-if="showModulesPagination"
          variant="bar"
          client
          :meta="modulesMeta"
          :per-page="modulesPerPage"
          :per-page-options="modulesPerPageOptions"
          @update:per-page="setModulesPerPage"
          @page-change="goToModulesPage"
        />
      </section>

      <aside class="space-y-4">
        <section class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5">
          <div class="flex items-start justify-between gap-2">
            <h2 class="font-display text-sm font-semibold text-slate-800">
              Checklist thiết lập
            </h2>
            <span class="shrink-0 text-[11px] tabular-nums text-slate-400">
              {{ checklistDone }}/{{ checklistTotal }}
            </span>
          </div>
          <ul class="mt-3 space-y-3">
            <li
              v-for="step in checklistItems"
              :key="step.key"
              class="flex gap-2.5"
              :class="step.planned ? 'opacity-70' : ''"
            >
              <AppIcon
                :name="step.done ? 'done' : (step.planned ? 'calendar' : 'system-config')"
                :size="15"
                class="mt-0.5 shrink-0"
                :class="step.done ? 'text-emerald-600' : 'text-slate-400'"
              />
              <div class="min-w-0 flex-1">
                <p
                  class="text-sm leading-snug"
                  :class="step.done ? 'font-medium text-slate-800' : (step.planned ? 'text-slate-400' : 'text-slate-700')"
                >
                  {{ step.label }}
                </p>
                <p
                  v-if="step.done_hint"
                  class="mt-0.5 text-[11px] leading-snug text-slate-400"
                >
                  {{ step.done_hint }}
                </p>
              </div>
            </li>
          </ul>
        </section>

        <section
          v-if="viewer.can_manage"
          class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm sm:p-5"
        >
          <h2 class="font-display text-sm font-semibold text-slate-800">
            Ghi chú nội bộ
          </h2>
          <p class="mt-1 text-[12px] leading-snug text-slate-500">
            Chỉ superadmin thấy — ghi chú cấu hình theo phòng ban.
          </p>
          <textarea
            v-model="notesForm.notes"
            rows="4"
            class="input mt-3 w-full text-sm leading-relaxed"
            :disabled="!canUpdate || notesForm.processing"
            placeholder="Ghi chú cấu hình…"
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
            v-else-if="workspace.status === 'missing'"
            class="mt-3 text-[12px] leading-snug text-amber-700"
          >
            Kích hoạt workspace trước để lưu ghi chú.
          </p>
        </section>
      </aside>
    </div>
  </AppLayout>
</template>
