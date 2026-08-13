<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import RoutineTaskSummaryBar from '@/modules/routine-task/components/RoutineTaskSummaryBar.vue';
import RoutineTaskCard from '@/modules/routine-task/components/RoutineTaskCard.vue';
import { useRoutineTasks } from '@/modules/routine-task/composables/useRoutineTasks';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    viewer: { type: Object, default: () => ({}) },
});

const { createTask, updateTask, toggleStatus, deleteTask } = useRoutineTasks();

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';
const ROUTINE_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'employee', label: 'Nhân sự', default: false },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(ROUTINE_FILTER_CONTROLS, 'va-workspace.routine-task.visible-filters.v1');

const filterPanelDdRef = ref(null);
const draftTitle = ref('');
const searchQ = ref(props.filters.q ?? '');
let searchTimer = null;

const filterForm = reactive({
    status: props.filters.status ?? '',
    employee: props.filters.employee ?? '',
});

watch(
    () => props.filters,
    (f) => {
        searchQ.value = f.q ?? '';
        filterForm.status = f.status ?? '';
        filterForm.employee = f.employee ?? '';
    },
    { deep: true },
);

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onToolbarClickOutside);
    if (searchTimer) clearTimeout(searchTimer);
});

const applyFilters = (overrides = {}) => {
    const params = {
        q: searchQ.value || undefined,
        status: filterForm.status || undefined,
        employee: filterForm.employee || undefined,
        ...overrides,
    };
    Object.keys(params).forEach((k) => {
        if (params[k] === '' || params[k] == null) delete params[k];
    });
    router.get('/routine-tasks', params, { preserveState: true, preserveScroll: true, replace: true });
};

const onSearchInput = (value) => {
    searchQ.value = value;
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilters(), 350);
};

const onQuickFilter = ({ status }) => {
    filterForm.status = status ?? '';
    applyFilters({ status: status || undefined });
};

const groups = computed(() => {
    const order = [
        { key: 'todo', label: 'Cần làm' },
        { key: 'in_progress', label: 'Đang làm' },
        { key: 'done', label: 'Hoàn thành' },
    ];
    return order.map((g) => ({
        ...g,
        items: (props.tasks ?? []).filter((t) => t.status?.value === g.key),
    })).filter((g) => g.items.length > 0 || !filterForm.status);
});

const pageTitle = computed(() => (
    props.viewer?.is_self === false && props.viewer?.target_name
        ? `Việc thường xuyên — ${props.viewer.target_name}`
        : 'Việc thường xuyên'
));

const canEdit = computed(() => Boolean(props.viewer?.is_self));

const submitNew = () => {
    const title = draftTitle.value.trim();
    if (!title || !props.viewer?.can_create) return;
    createTask({ title }, {
        onSuccess: () => { draftTitle.value = ''; },
    });
};
</script>

<template>
  <Head :title="pageTitle" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="pageTitle"
        subtitle="Checklist dài hạn — theo dõi tiến độ việc lặp lại, độc lập với dự án"
        icon="list"
        icon-color="brand"
        :badge="summary.total ?? null"
      />
    </template>

    <RoutineTaskSummaryBar
      :summary="summary"
      :active-status="filterForm.status"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-hidden">
      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="searchQ"
              input-id="routine-task-search"
              input-name="q"
              placeholder="Tìm theo tiêu đề hoặc mô tả…"
              stretch
              inline-actions
              hide-label
              input-height="h-10"
              @update:model-value="onSearchInput"
            />
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                @click="openFilterPanel()"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="FILTER_CONTROLS"
                input-id-prefix="routine-filter-vis"
                @persist="persistVisibleFilters"
              />
            </div>
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
        >
          <DatagridFilterField
            v-if="visibleFilters.status"
          >
            <select
              v-model="filterForm.status"
              :class="FILTER_CONTROL_CLASS"
              @change="applyFilters()"
            >
              <option value="">
                Trạng thái
              </option>
              <option
                v-for="s in (options.statuses ?? [])"
                :key="s.value"
                :value="s.value"
              >
                {{ s.label }}
              </option>
            </select>
          </DatagridFilterField>

          <DatagridFilterField
            v-if="visibleFilters.employee && viewer.can_view_others"
          >
            <select
              v-model="filterForm.employee"
              :class="FILTER_CONTROL_CLASS"
              @change="applyFilters()"
            >
              <option value="">
                Nhân sự
              </option>
              <option
                v-for="e in (options.employees ?? [])"
                :key="e.id"
                :value="e.id"
              >
                {{ e.name ?? e.full_name ?? e.label }}
              </option>
            </select>
          </DatagridFilterField>
        </div>
      </div>

      <div
        v-if="viewer.can_create"
        class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/60 px-5 py-3"
      >
        <input
          v-model="draftTitle"
          type="text"
          class="input h-10 min-w-0 flex-1 text-sm"
          maxlength="255"
          placeholder="Thêm việc thường xuyên mới…"
          @keydown.enter.prevent="submitNew"
        >
        <button
          type="button"
          class="btn-primary h-10 shrink-0 gap-1.5 px-3 text-sm"
          :disabled="!draftTitle.trim()"
          @click="submitNew"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm
        </button>
      </div>

      <div class="space-y-6 px-5 py-5">
        <div
          v-if="!(tasks ?? []).length"
          class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-10 text-center"
        >
          <AppIcon
            name="list"
            :size="28"
            class="mx-auto text-slate-300"
          />
          <p class="mt-3 text-sm font-medium text-slate-700">
            Chưa có việc thường xuyên
          </p>
          <p class="mt-1 text-xs text-slate-500">
            Thêm việc lặp lại tại đây, hoặc gắn từ tab «Công việc thường xuyên» trong báo cáo hôm nay.
          </p>
        </div>

        <section
          v-for="group in groups"
          :key="group.key"
          class="space-y-2"
        >
          <div class="flex items-center gap-2">
            <h2 class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">
              {{ group.label }}
            </h2>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold tabular-nums text-slate-600">
              {{ group.items.length }}
            </span>
          </div>
          <div
            v-if="group.items.length"
            class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3"
          >
            <RoutineTaskCard
              v-for="task in group.items"
              :key="task.id"
              :task="task"
              :can-edit="canEdit && Boolean(task.can?.update)"
              @toggle-status="toggleStatus(task.id)"
              @update-title="(title) => updateTask(task.id, { title })"
              @delete="deleteTask(task.id)"
            />
          </div>
          <p
            v-else
            class="text-xs text-slate-400"
          >
            Chưa có việc ở nhóm này
          </p>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
