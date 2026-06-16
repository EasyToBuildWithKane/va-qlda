<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import SearchMultiSelect from '@/shared/ui/SearchMultiSelect.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';

/**
 * Bộ lọc hiệu suất — datagrid toolbar (dùng chung Dashboard & Audit).
 */
const props = defineProps({
    filter: { type: Object, required: true },
    options: { type: Object, required: true },
    requireMember: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['export-excel']);

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const FILTER_CONTROLS_DEF = [
    { key: 'anchor_date', label: 'Mốc thời gian', default: true },
    { key: 'sprint', label: 'Sprint', default: true },
    { key: 'department', label: 'Phòng ban', default: false },
    { key: 'team', label: 'Team', default: false },
    { key: 'member', label: 'Thành viên', default: true },
    { key: 'project', label: 'Dự án', default: false },
    { key: 'status', label: 'Trạng thái task', default: false },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(
    FILTER_CONTROLS_DEF,
    'va-qlda.performance.visible-filters.v2',
);

const filterPanelDdRef = ref(null);
const exportRef = ref(null);
const exportMenu = ref(false);

const { panelStyle: exportPanelStyle } = useFixedDropdownAnchor(
    () => exportRef.value,
    exportMenu,
    { width: 208, zIndex: 85 },
);

const state = reactive({
    period: props.filter.period ?? 'month',
    date: props.filter.date ?? '',
    sprint: props.filter.sprint ?? null,
    department: props.filter.department ?? null,
    team: props.filter.team ?? null,
    member: props.filter.member ?? null,
    project: props.filter.project ?? null,
    status: Array.isArray(props.filter.status) ? [...props.filter.status] : [],
});

let timer = null;
let suppress = false;

function apply() {
    const params = {};
    Object.entries(state).forEach(([k, v]) => {
        if (v === null || v === '' || (Array.isArray(v) && v.length === 0)) return;
        params[k] = v;
    });
    router.get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

watch(state, () => {
    if (suppress) return;
    clearTimeout(timer);
    timer = setTimeout(apply, 250);
}, { deep: true });

const periodTabs = computed(() =>
    (props.options.periods ?? []).map((p) => ({
        key: p.value,
        label: p.label,
    })),
);

function onPeriodChange(p) {
    suppress = true;
    state.period = p;
    if (p !== 'sprint') state.sprint = null;
    suppress = false;
    apply();
}

function reset() {
    suppress = true;
    state.period = 'month';
    state.date = new Date().toISOString().slice(0, 10);
    state.sprint = null;
    state.department = null;
    state.team = null;
    state.member = null;
    state.project = null;
    state.status = [];
    suppress = false;
    apply();
}

const statusOptions = computed(() =>
    (props.options.statuses ?? []).map((s) => ({ value: s.value, label: s.label })),
);

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (e.target.closest?.('[data-performance-toolbar-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (exportMenu.value && exportRef.value && !exportRef.value.contains(e.target)) {
        exportMenu.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function openExportMenu() {
    exportMenu.value = !exportMenu.value;
    if (exportMenu.value) showFilterPanelDd.value = false;
}

function runExport() {
    exportMenu.value = false;
    emit('export-excel');
}
</script>

<template>
  <div class="card mb-5 overflow-visible print:hidden">
    <div class="border-b border-slate-100 px-5 py-4">
      <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
        <div class="flex shrink-0 items-center gap-2">
          <div
            ref="filterPanelDdRef"
            class="relative shrink-0"
          >
            <DatagridToolbarActionButton
              icon="filter"
              :active="showFilterPanelDd"
              :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
              @click="openFilterPanel(() => { exportMenu = false; })"
            >
              Lọc
            </DatagridToolbarActionButton>
            <FilterVisibilityDropdown
              v-model="visibleFilters"
              :show="showFilterPanelDd"
              :anchor-ref="filterPanelDdRef"
              :controls="FILTER_CONTROLS"
              @persist="persistVisibleFilters"
            />
          </div>

          <div
            ref="exportRef"
            class="relative shrink-0"
          >
            <DatagridToolbarActionButton
              icon="export"
              :active="exportMenu"
              title="Xuất báo cáo"
              @click="openExportMenu"
            >
              Xuất
            </DatagridToolbarActionButton>
            <div
              v-if="exportMenu"
              data-performance-toolbar-panel
              class="fixed z-[85] rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
              :style="exportPanelStyle"
            >
              <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                @click="runExport"
              >
                <AppIcon
                  name="export"
                  :size="16"
                />
                Excel (.xlsx)
              </button>
            </div>
          </div>

          <button
            type="button"
            class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-lg border border-slate-200 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50"
            title="Đặt lại bộ lọc"
            @click="reset"
          >
            <AppIcon
              name="refresh"
              :size="14"
            />
            Đặt lại
          </button>

          <slot name="actions" />
        </div>

        <div class="ml-auto flex shrink-0 items-center gap-2">
          <DatagridSegmentedControl
            v-if="periodTabs.length"
            :model-value="state.period"
            :items="periodTabs"
            aria-label="Chọn kỳ báo cáo"
            icon-only-below-sm
            @update:model-value="onPeriodChange"
          />
        </div>
      </div>

      <div
        v-if="hasFilterRow"
        class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
      >
        <DatagridFilterField
          v-if="visibleFilters.anchor_date && state.period !== 'sprint'"
          class="sm:col-span-2 xl:col-span-2"
        >
          <FilterDatePicker
            v-model="state.date"
            placeholder="Mốc thời gian"
          />
        </DatagridFilterField>

        <DatagridFilterField
          v-if="visibleFilters.sprint && state.period === 'sprint'"
          class="sm:col-span-2 xl:col-span-2"
        >
          <select
            v-model="state.sprint"
            :class="FILTER_CONTROL_CLASS"
            aria-label="Sprint"
          >
            <option :value="null">
              Sprint
            </option>
            <option
              v-for="s in options.sprints"
              :key="s.value"
              :value="s.value"
            >
              {{ s.label }}
            </option>
          </select>
        </DatagridFilterField>

        <DatagridFilterField v-if="visibleFilters.department">
          <select
            v-model="state.department"
            :class="FILTER_CONTROL_CLASS"
            aria-label="Phòng ban"
          >
            <option :value="null">
              Phòng ban
            </option>
            <option
              v-for="d in options.departments"
              :key="d.value"
              :value="d.value"
            >
              {{ d.label }}
            </option>
          </select>
        </DatagridFilterField>

        <DatagridFilterField v-if="visibleFilters.team">
          <select
            v-model="state.team"
            :class="FILTER_CONTROL_CLASS"
            aria-label="Team"
          >
            <option :value="null">
              Team
            </option>
            <option
              v-for="t in options.teams"
              :key="t.value"
              :value="t.value"
            >
              {{ t.label }}
            </option>
          </select>
        </DatagridFilterField>

        <DatagridFilterField v-if="visibleFilters.member">
          <select
            v-model="state.member"
            :class="FILTER_CONTROL_CLASS"
            aria-label="Thành viên"
          >
            <option :value="null">
              {{ requireMember ? 'Thành viên' : 'Thành viên' }}
            </option>
            <option
              v-for="m in options.members"
              :key="m.value"
              :value="m.value"
            >
              {{ m.label }}
            </option>
          </select>
        </DatagridFilterField>

        <DatagridFilterField v-if="visibleFilters.project">
          <select
            v-model="state.project"
            :class="FILTER_CONTROL_CLASS"
            aria-label="Dự án"
          >
            <option :value="null">
              Dự án
            </option>
            <option
              v-for="p in options.projects"
              :key="p.value"
              :value="p.value"
            >
              {{ p.label }}
            </option>
          </select>
        </DatagridFilterField>

        <DatagridFilterField
          v-if="visibleFilters.status && !requireMember"
          class="sm:col-span-2 xl:col-span-2"
        >
          <SearchMultiSelect
            v-model="state.status"
            :options="statusOptions"
            value-key="value"
            label-key="label"
            placeholder="Trạng thái task"
            control-size="md"
          />
        </DatagridFilterField>
      </div>

      <div
        v-if="processing"
        class="mt-3 h-0.5 w-full overflow-hidden rounded-full bg-slate-100"
      >
        <div class="h-full w-1/3 animate-pulse rounded-full bg-brand" />
      </div>
    </div>
  </div>
</template>
