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
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import RoutineTaskSummaryBar from '@/modules/routine-task/components/RoutineTaskSummaryBar.vue';
import RoutineTaskListRow from '@/modules/routine-task/components/RoutineTaskListRow.vue';
import RoutineTaskPeopleBar from '@/modules/routine-task/components/RoutineTaskPeopleBar.vue';
import RoutineTaskFormModal from '@/modules/routine-task/components/RoutineTaskFormModal.vue';
import { useRoutineTasks, todayIso, formatViDate } from '@/modules/routine-task/composables/useRoutineTasks';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    viewer: { type: Object, default: () => ({}) },
});

const { toggleStatus, deleteTask } = useRoutineTasks();

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';
const ROUTINE_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'employee', label: 'Nhân sự', default: false },
    { key: 'date_range', label: 'Khoảng ngày', default: false },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(ROUTINE_FILTER_CONTROLS, 'va-workspace.routine-task.visible-filters.v2');

const filterPanelDdRef = ref(null);
const searchQ = ref(props.filters.q ?? '');
const showForm = ref(false);
const editingTask = ref(null);
let searchTimer = null;

const filterForm = reactive({
    status: props.filters.status ?? '',
    employee: props.filters.employee ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

watch(
    () => props.filters,
    (f) => {
        searchQ.value = f.q ?? '';
        filterForm.status = f.status ?? '';
        filterForm.employee = f.employee ?? '';
        filterForm.from = f.from ?? '';
        filterForm.to = f.to ?? '';
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
        from: filterForm.from || undefined,
        to: filterForm.to || undefined,
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

const onSelectPerson = (employeeId) => {
    filterForm.employee = employeeId || '';
    applyFilters({ employee: employeeId || undefined });
};

const groups = computed(() => {
    const today = todayIso();
    const y = new Date(`${today}T00:00:00`);
    y.setDate(y.getDate() - 1);
    const yesterday = `${y.getFullYear()}-${String(y.getMonth() + 1).padStart(2, '0')}-${String(y.getDate()).padStart(2, '0')}`;

    const buckets = new Map();
    const ensure = (key, label) => {
        if (!buckets.has(key)) buckets.set(key, { key, label, items: [] });
        return buckets.get(key);
    };

    (props.tasks ?? []).forEach((task) => {
        const d = task.work_date || '';
        if (!d) {
            ensure('undated', 'Việc lặp lại').items.push(task);
        } else if (d === today) {
            ensure('today', 'Hôm nay').items.push(task);
        } else if (d === yesterday) {
            ensure('yesterday', 'Hôm qua').items.push(task);
        } else {
            ensure(d, formatViDate(d) || d).items.push(task);
        }
    });

    return [...buckets.values()].sort((a, b) => {
        const rank = (g) => {
            if (g.key === 'today') return 0;
            if (g.key === 'yesterday') return 1;
            if (g.key === 'undated') return 100;
            return 10;
        };
        const ra = rank(a);
        const rb = rank(b);
        if (ra !== rb) return ra - rb;
        return String(b.key).localeCompare(String(a.key));
    });
});

const pageTitle = computed(() => (
    props.viewer?.is_self === false && props.viewer?.target_name
        ? `Việc thường xuyên — ${props.viewer.target_name}`
        : 'Việc thường xuyên'
));

const canEdit = computed(() => Boolean(props.viewer?.is_self));

function openCreate() {
    editingTask.value = null;
    showForm.value = true;
}

function openTask(task) {
    editingTask.value = task;
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingTask.value = null;
}
</script>

<template>
  <Head :title="pageTitle" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="pageTitle"
        subtitle="Nhật ký công việc hằng ngày — giờ ET, tiến độ, vướng mắc"
        icon="list"
        icon-color="brand"
        :badge="summary.total ?? null"
      >
        <button
          v-if="viewer.can_create"
          type="button"
          class="btn-primary inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-xs font-semibold"
          @click="openCreate"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm
        </button>
      </PageHeader>
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
              placeholder="Tìm theo tiêu đề, mô tả, vướng mắc…"
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

          <DatagridFilterField
            v-if="visibleFilters.date_range"
            class="sm:col-span-2 xl:col-span-2"
          >
            <div class="grid grid-cols-2 gap-2">
              <FilterDatePicker
                v-model="filterForm.from"
                placeholder="Từ ngày"
                @update:model-value="applyFilters()"
              />
              <FilterDatePicker
                v-model="filterForm.to"
                placeholder="Đến ngày"
                @update:model-value="applyFilters()"
              />
            </div>
          </DatagridFilterField>
        </div>

        <div
          v-if="viewer.can_view_others"
          class="mt-3"
        >
          <RoutineTaskPeopleBar
            :self="viewer.self"
            :reports="options.direct_reports ?? []"
            :active-employee-id="filterForm.employee || viewer.employee_id"
            @select="onSelectPerson"
          />
        </div>
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
            Chưa có công việc
          </p>
          <p class="mt-1 text-xs text-slate-500">
            Bấm «Thêm» để ghi nhận việc trong ngày — họp, tác vụ lặp, tiến độ và vướng mắc.
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
            class="space-y-2"
          >
            <RoutineTaskListRow
              v-for="task in group.items"
              :key="task.id"
              :task="task"
              :can-edit="canEdit && Boolean(task.can?.update)"
              @toggle-status="toggleStatus(task.id)"
              @open="openTask(task)"
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

    <RoutineTaskFormModal
      :show="showForm"
      :task="editingTask"
      :statuses="options.statuses ?? []"
      :can-edit="canEdit && (editingTask ? Boolean(editingTask.can?.update) : Boolean(viewer.can_create))"
      @close="closeForm"
    />
  </AppLayout>
</template>
