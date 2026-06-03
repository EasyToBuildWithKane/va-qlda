<script setup>
import { reactive, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import BlockerFormModal from '@/modules/project/components/BlockerFormModal.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { date } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    blockers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const modal = ref(false);
const editing = ref(null);
const filterPanelDdRef = ref(null);

const BLOCKER_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái' },
    { key: 'severity', label: 'Mức độ' },
    { key: 'project', label: 'Dự án' },
    { key: 'mine', label: 'Tôi xử lý' },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(BLOCKER_FILTER_CONTROLS, 'va-qlda.blockers.visible-filters');

const open = (b = null) => { editing.value = b; modal.value = true; };

function onToolbarClickOutside(e) {
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    severity: props.filters.severity ?? '',
    project_id: props.filters.project_id ?? '',
    mine: props.filters.mine ? '1' : '',
});

const activeCount = computed(() =>
    Object.entries(filterForm).filter(([k, v]) => k !== 'q' && v !== '' && v != null).length,
);

function routeParams() {
    return {
        q: filterForm.q || undefined,
        status: filterForm.status || undefined,
        severity: filterForm.severity || undefined,
        project_id: filterForm.project_id || undefined,
        mine: filterForm.mine || undefined,
    };
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => {
        router.get('/blockers', routeParams(), { preserveState: true, replace: true });
    }, 350);
});

watch(
    () => [filterForm.status, filterForm.severity, filterForm.project_id, filterForm.mine],
    () => {
        router.get('/blockers', routeParams(), { preserveState: true, replace: true });
    },
);

const clearFilters = () => {
    filterForm.q = '';
    filterForm.status = '';
    filterForm.severity = '';
    filterForm.project_id = '';
    filterForm.mine = '';
};

const resolve = (b) => router.put(`/blockers/${b.id}`, { status: 'resolved' }, { preserveScroll: true });
const remove = async (b) => {
    if (await dialog.confirm({ title: 'Xoá vướng mắc', message: `Xoá "${b.title}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/blockers/${b.id}`, { preserveScroll: true });
};
</script>

<template>
  <Head title="Vướng mắc" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Vướng mắc cần xử lý"
        subtitle="Theo dõi và giải quyết các vướng mắc trong dự án"
        icon="blockers"
        icon-color="amber"
        :badge="summary.open ?? null"
      />
    </template>

    <div class="card mb-4 overflow-visible">
      <div class="border-b border-slate-100 px-5 py-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="blockers-search"
              placeholder="Tiêu đề, mô tả vướng mắc…"
            />
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition"
                :class="showFilterPanelDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                @click="openFilterPanel()"
              >
                <AppIcon
                  name="filter"
                  :size="15"
                />
                <span>Lọc</span>
              </button>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :controls="FILTER_CONTROLS"
                @persist="persistVisibleFilters"
              />
            </div>
          </div>
          <button
            v-if="can.create"
            type="button"
            class="btn-primary h-9 shrink-0 gap-1.5 px-4 text-sm"
            @click="open()"
          >
            <AppIcon
              name="add"
              :size="15"
            />
            Ghi nhận vướng mắc
          </button>
        </div>
      </div>

      <div
        v-if="hasFilterRow"
        class="flex flex-wrap items-center gap-2 border-t border-slate-100 px-5 py-3"
      >
        <select
          v-if="visibleFilters.status"
          v-model="filterForm.status"
          class="input h-9 w-40 text-sm"
          aria-label="Trạng thái"
        >
          <option value="">
            Trạng thái: Tất cả
          </option>
          <option
            v-for="o in options.status"
            :key="o.value"
            :value="o.value"
          >
            {{ o.label }}
          </option>
        </select>
        <select
          v-if="visibleFilters.severity"
          v-model="filterForm.severity"
          class="input h-9 w-40 text-sm"
          aria-label="Mức độ"
        >
          <option value="">
            Mức độ: Tất cả
          </option>
          <option
            v-for="o in options.severity"
            :key="o.value"
            :value="o.value"
          >
            {{ o.label }}
          </option>
        </select>
        <select
          v-if="visibleFilters.project"
          v-model="filterForm.project_id"
          class="input h-9 min-w-[11rem] text-sm sm:w-52"
          aria-label="Dự án"
        >
          <option value="">
            Dự án: Tất cả
          </option>
          <option
            v-for="p in options.projects"
            :key="p.id"
            :value="p.id"
          >
            {{ p.name }}
          </option>
        </select>
        <label
          v-if="visibleFilters.mine"
          class="inline-flex h-9 items-center gap-2 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600"
        >
          <input
            v-model="filterForm.mine"
            true-value="1"
            false-value=""
            type="checkbox"
            class="rounded border-slate-300 text-brand"
          >
          Tôi xử lý
        </label>
        <button
          v-if="activeCount || filterForm.q"
          type="button"
          class="text-xs font-medium text-brand hover:underline"
          @click="clearFilters"
        >
          Đặt lại
        </button>
      </div>
    </div>

    <div class="space-y-2.5">
      <div
        v-for="b in blockers.data"
        :key="b.id"
        class="card p-4 transition hover:border-slate-300"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <Badge
                :label="b.severity.label"
                :color="b.severity.color"
              />
              <Badge
                :label="b.status.label"
                :color="b.status.color"
              />
              <span
                v-if="b.project"
                class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500"
              >
                {{ b.project.name }}
              </span>
              <span class="font-medium text-slate-800">{{ b.title }}</span>
            </div>
            <p
              v-if="b.description"
              class="mt-1 line-clamp-2 text-sm text-slate-500"
            >
              {{ b.description }}
            </p>
            <p class="mt-1.5 flex flex-wrap items-center gap-x-1.5 text-xs text-slate-400">
              <AppIcon
                name="calendar"
                :size="13"
              />
              {{ date(b.raised_at) }}
              <span class="text-slate-300">·</span>
              Báo bởi {{ b.raised_by?.name || '—' }}
              <template v-if="b.owner">
                <span class="text-slate-300">·</span>
                Xử lý: {{ b.owner.name }}
              </template>
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-1">
            <button
              v-if="b.can?.update && b.status.value !== 'resolved'"
              type="button"
              class="btn-ghost gap-1 text-xs text-emerald-600"
              @click="resolve(b)"
            >
              <AppIcon
                name="done"
                :size="14"
              />
              Đã xử lý
            </button>
            <button
              v-if="b.can?.update"
              type="button"
              class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700"
              @click="open(b)"
            >
              <AppIcon
                name="edit"
                :size="15"
              />
            </button>
            <button
              v-if="b.can?.delete"
              type="button"
              class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600"
              @click="remove(b)"
            >
              <AppIcon
                name="delete"
                :size="15"
              />
            </button>
          </div>
        </div>
      </div>
      <div
        v-if="!blockers.data?.length"
        class="card p-10 text-center text-sm text-slate-400"
      >
        Không có vướng mắc phù hợp bộ lọc.
      </div>
    </div>

    <BlockerFormModal
      :show="modal"
      :blocker="editing"
      :projects="options.projects"
      :employees="options.employees"
      :severity-options="options.severity"
      :status-options="options.status"
      @close="modal = false"
    />
  </AppLayout>
</template>
