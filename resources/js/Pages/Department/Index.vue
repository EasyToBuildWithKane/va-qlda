<script setup>
import { ref, computed, reactive, watch, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import DepartmentFormModal from '@/modules/project/components/DepartmentFormModal.vue';
import ProjectMembers from '@/modules/project/components/ProjectMembers.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useDialog } from '@/composables/useDialog';
import { date, datetime } from '@/composables/useFormat';

const PER_PAGE_OPTIONS = [5, 10, 15, 20];
const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const props = defineProps({
    departments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({ total: 0, active: 0, inactive: 0 }) },
    colorOptions: { type: Array, default: () => [] },
    existingDepartments: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const modal = ref(false);
const editing = ref(null);
const open = (d = null) => { editing.value = d; modal.value = true; };

const swatch = {
    brand: 'bg-brand', sky: 'bg-sky-500', emerald: 'bg-emerald-500',
    violet: 'bg-violet-500', amber: 'bg-amber-500', rose: 'bg-rose-500',
    cyan: 'bg-cyan-500', slate: 'bg-slate-400',
};

const COLOR_LABELS = Object.fromEntries(
    props.colorOptions.map((o) => [o.value, o.label]),
);

const TABLE_COLUMNS = [
    { key: 'code', label: 'Mã', default: false },
    { key: 'color', label: 'Màu', default: false },
    { key: 'manager', label: 'Trưởng phòng', default: true },
    { key: 'members', label: 'Thành viên', default: true },
    { key: 'projects', label: 'Số dự án', default: true },
    { key: 'sort_order', label: 'Thứ tự', default: false },
    { key: 'status', label: 'Trạng thái', default: true },
    { key: 'created_at', label: 'Ngày tạo', default: false },
    { key: 'updated_at', label: 'Cập nhật', default: false },
];

const {
    visibleCols,
    showColDd,
    visibleColumnCount,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
} = useVisibleColumns(TABLE_COLUMNS, 'va-qlda.departments.columns');

const totalCols = computed(() => 2 + visibleColumnCount.value);

const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    manager_id: props.filters.manager_id ?? '',
    color: props.filters.color ?? '',
    has_projects: props.filters.has_projects ?? '',
});

const perPage = ref(Number(props.filters.per_page) || props.departments.meta?.per_page || 10);

function routeParams(resetPage = false) {
    const params = Object.fromEntries(
        Object.entries({ ...filterForm, per_page: perPage.value }).filter(([, v]) => v !== '' && v != null),
    );
    if (resetPage) params.page = 1;
    return params;
}

const applyFilters = (resetPage = true) => {
    router.get('/departments', routeParams(resetPage), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
};

function onPerPageChange(n) {
    perPage.value = n;
    applyFilters(true);
}

let kwTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(kwTimer);
    kwTimer = setTimeout(() => applyFilters(true), 350);
});

watch(
    () => [filterForm.status, filterForm.manager_id, filterForm.color, filterForm.has_projects],
    () => applyFilters(true),
);

const activeFilterCount = computed(() =>
    Object.entries(filterForm).filter(([k, v]) => k !== 'q' && v !== '' && v != null).length,
);

const hasAnyFilter = computed(() =>
    activeFilterCount.value > 0 || filterForm.q.trim() !== '',
);

const clearAll = async () => {
    if (!hasAnyFilter.value) return;
    if (!await dialog.confirm({
        title: 'Xoá bộ lọc',
        message: 'Xoá tất cả bộ lọc đang áp dụng?',
        confirmText: 'Xoá lọc',
    })) return;
    resetFilters();
};

function resetFilters() {
    Object.keys(filterForm).forEach((k) => { filterForm[k] = ''; });
    applyFilters(true);
}

const DEPT_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'manager', label: 'Trưởng phòng', default: false },
    { key: 'color', label: 'Màu', default: false },
    { key: 'has_projects', label: 'Dự án gán', default: false },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(DEPT_FILTER_CONTROLS, 'va-qlda.departments.visible-filters.v2');

const filterDdRef = ref(null);
const colDdRef = ref(null);

const onDocClick = (e) => {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (e.target.closest?.('[data-column-visibility-panel]')) return;
    if (filterDdRef.value && !filterDdRef.value.contains(e.target)) showFilterPanelDd.value = false;
    if (colDdRef.value && !colDdRef.value.contains(e.target)) showColDd.value = false;
};
onMounted(() => document.addEventListener('mousedown', onDocClick));
onUnmounted(() => document.removeEventListener('mousedown', onDocClick));

const openFilter = () => { openFilterPanel(() => { showColDd.value = false; }); };
const openCol = () => { openColPanel(() => { showFilterPanelDd.value = false; }); };

const remove = async (d) => {
    const msg = d.project_count
        ? `Xoá "${d.name}"? ${d.project_count} dự án sẽ bị bỏ gán phòng ban (không xoá dự án).`
        : `Xoá phòng ban "${d.name}"?`;
    if (await dialog.confirm({ title: 'Xoá phòng ban', message: msg, tone: 'danger', confirmText: 'Xoá' })) {
        router.delete(`/departments/${d.id}`, { preserveScroll: true });
    }
};

const toggleStatus = async (d) => {
    const toActive = !d.is_active;
    const confirmed = await dialog.confirm({
        title: toActive ? 'Kích hoạt phòng ban' : 'Ngừng hoạt động',
        message: toActive
            ? `Kích hoạt "${d.name}"? Phòng ban sẽ xuất hiện khi tạo dự án mới.`
            : `Ngừng hoạt động "${d.name}"? Phòng ban sẽ bị ẩn khi tạo dự án mới.`,
        confirmText: toActive ? 'Kích hoạt' : 'Ngừng',
        tone: toActive ? 'default' : 'danger',
    });
    if (!confirmed) return;
    router.patch(`/departments/${d.id}/toggle`, {}, { preserveScroll: true });
};
</script>

<template>
  <Head title="Phòng ban" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý phòng ban"
        subtitle="Cơ cấu tổ chức và phân công nhân sự"
        icon="department"
        icon-color="sky"
        :badge="summary.total"
      >
        <button
          v-if="can.create"
          type="button"
          class="btn-primary inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-xs font-semibold"
          @click="open()"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm phòng ban
        </button>
      </PageHeader>
    </template>

    <div class="card overflow-visible">
      <div class="flex items-center border-b border-slate-100 px-5 py-4">
        <div class="flex items-center gap-2">
          <h2 class="font-semibold text-slate-700">
            Danh sách phòng ban
          </h2>
          <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-brand/10 px-1.5 text-[11px] font-bold text-brand">
            {{ departments.meta?.total ?? summary.total }}
          </span>
        </div>
      </div>

      <div class="border-b border-slate-100 bg-slate-50/40 px-5 py-3.5 lg:py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="departments-search"
              placeholder="Tên, mã, trưởng phòng…"
              stretch
              inline-actions
              hide-label
              input-height="h-10"
            />
          </div>

          <div class="flex shrink-0 items-center gap-2">
            <div
              ref="filterDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="filter"
                :active="showFilterPanelDd"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                @click="openFilter"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterDdRef"
                :controls="FILTER_CONTROLS"
                @persist="persistVisibleFilters"
              />
            </div>

            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <DatagridToolbarActionButton
                icon="columns"
                :active="showColDd"
                title="Cột hiển thị"
                @click="openCol"
              >
                Cột
              </DatagridToolbarActionButton>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :anchor-ref="colDdRef"
                :columns="TABLE_COLUMNS"
                :fixed-labels="['Phòng ban', 'Thao tác']"
                @persist="persistVisibleColumns"
              />
            </div>
          </div>
        </div>

        <p
          v-if="!hasFilterRow && hasAnyFilter"
          class="mt-2 text-xs text-slate-500"
        >
          <span v-if="activeFilterCount > 0">{{ activeFilterCount }} bộ lọc đang áp dụng</span>
          <span v-if="filterForm.q.trim()"><span v-if="activeFilterCount > 0"> · </span>«{{ filterForm.q.trim() }}»</span>
        </p>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
          >
            <DatagridFilterField v-if="visibleFilters.status">
              <label
                for="dept-filter-status"
                class="sr-only"
              >Trạng thái</label>
              <select
                id="dept-filter-status"
                v-model="filterForm.status"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Trạng thái
                </option>
                <option value="active">
                  Hoạt động
                </option>
                <option value="inactive">
                  Ngừng
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.manager">
              <label
                for="dept-filter-manager"
                class="sr-only"
              >Trưởng phòng</label>
              <select
                id="dept-filter-manager"
                v-model="filterForm.manager_id"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Trưởng phòng
                </option>
                <option
                  v-for="e in employees"
                  :key="e.id"
                  :value="e.id"
                >
                  {{ e.name }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.color">
              <label
                for="dept-filter-color"
                class="sr-only"
              >Màu</label>
              <select
                id="dept-filter-color"
                v-model="filterForm.color"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Màu
                </option>
                <option
                  v-for="c in colorOptions"
                  :key="c.value"
                  :value="c.value"
                >
                  {{ c.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.has_projects">
              <label
                for="dept-filter-projects"
                class="sr-only"
              >Dự án gán</label>
              <select
                id="dept-filter-projects"
                v-model="filterForm.has_projects"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Dự án gán
                </option>
                <option value="yes">
                  Có dự án
                </option>
                <option value="no">
                  Chưa có dự án
                </option>
              </select>
            </DatagridFilterField>

            <div
              v-if="hasAnyFilter"
              class="col-span-full flex justify-end pt-0.5"
            >
              <button
                type="button"
                class="inline-flex h-10 items-center px-2 text-xs font-medium text-brand hover:underline"
                @click="resetFilters"
              >
                Đặt lại bộ lọc
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-slate-100 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">
                Phòng ban
              </th>
              <th
                v-if="isColVisible('code')"
                class="px-5 py-3"
              >
                Mã
              </th>
              <th
                v-if="isColVisible('color')"
                class="px-5 py-3"
              >
                Màu
              </th>
              <th
                v-if="isColVisible('manager')"
                class="px-5 py-3"
              >
                Trưởng phòng
              </th>
              <th
                v-if="isColVisible('members')"
                class="px-5 py-3"
              >
                Thành viên
              </th>
              <th
                v-if="isColVisible('projects')"
                class="px-5 py-3 text-center"
              >
                Dự án
              </th>
              <th
                v-if="isColVisible('sort_order')"
                class="px-5 py-3 text-center"
              >
                Thứ tự
              </th>
              <th
                v-if="isColVisible('status')"
                class="px-5 py-3 text-center"
              >
                Trạng thái
              </th>
              <th
                v-if="isColVisible('created_at')"
                class="px-5 py-3"
              >
                Ngày tạo
              </th>
              <th
                v-if="isColVisible('updated_at')"
                class="px-5 py-3"
              >
                Cập nhật
              </th>
              <th class="w-24 px-5 py-3" />
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="d in departments.data"
              :key="d.id"
              class="group transition-colors hover:bg-slate-50/70"
            >
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                    :class="swatch[d.color] ?? swatch.slate"
                  >
                    <AppIcon
                      name="department"
                      :size="16"
                      class="text-white/90"
                    />
                  </span>
                  <div class="min-w-0">
                    <p class="truncate font-medium leading-snug text-slate-800">
                      {{ d.name }}
                    </p>
                    <p
                      v-if="!isColVisible('code')"
                      class="font-mono text-[11px] tracking-wider text-slate-400"
                    >
                      {{ d.code }}
                    </p>
                  </div>
                </div>
              </td>

              <td
                v-if="isColVisible('code')"
                class="px-5 py-3.5 font-mono text-xs text-slate-600"
              >
                {{ d.code }}
              </td>

              <td
                v-if="isColVisible('color')"
                class="px-5 py-3.5"
              >
                <span class="inline-flex items-center gap-2 text-xs text-slate-600">
                  <span
                    class="h-4 w-4 rounded"
                    :class="swatch[d.color] ?? swatch.slate"
                  />
                  {{ COLOR_LABELS[d.color] ?? d.color }}
                </span>
              </td>

              <td
                v-if="isColVisible('manager')"
                class="px-5 py-3.5"
              >
                <div
                  v-if="d.manager"
                  class="flex items-center gap-2"
                >
                  <Avatar
                    :name="d.manager.name"
                    :src="d.manager.avatar_path"
                    :size="26"
                  />
                  <span class="truncate text-sm text-slate-700">{{ d.manager.name }}</span>
                </div>
                <span
                  v-else
                  class="text-xs text-slate-300"
                >Chưa phân công</span>
              </td>

              <td
                v-if="isColVisible('members')"
                class="px-5 py-3.5"
              >
                <ProjectMembers
                  :members="d.members ?? []"
                  compact
                  :max-visible="4"
                  :max-name-labels="2"
                />
                <p
                  v-if="(d.member_count ?? 0) > (d.members?.length ?? 0)"
                  class="mt-0.5 text-[10px] text-slate-400"
                >
                  Tổng {{ d.member_count }} người
                </p>
              </td>

              <td
                v-if="isColVisible('projects')"
                class="px-5 py-3.5 text-center"
              >
                <span
                  class="inline-flex min-w-[28px] items-center justify-center rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="(d.project_count ?? 0) > 0 ? 'bg-brand/10 text-brand' : 'bg-slate-100 text-slate-400'"
                >
                  {{ d.project_count ?? 0 }}
                </span>
              </td>

              <td
                v-if="isColVisible('sort_order')"
                class="px-5 py-3.5 text-center tabular-nums text-slate-600"
              >
                {{ d.sort_order ?? 0 }}
              </td>

              <td
                v-if="isColVisible('status')"
                class="px-5 py-3.5 text-center"
              >
                <button
                  v-if="d.can?.update"
                  type="button"
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium transition hover:shadow-sm"
                  :class="d.is_active
                    ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200'
                    : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                  :title="d.is_active ? 'Nhấn để ngừng hoạt động' : 'Nhấn để kích hoạt'"
                  @click="toggleStatus(d)"
                >
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="d.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
                  />
                  {{ d.is_active ? 'Hoạt động' : 'Ngừng' }}
                </button>
                <span
                  v-else
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                  :class="d.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                >
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="d.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
                  />
                  {{ d.is_active ? 'Hoạt động' : 'Ngừng' }}
                </span>
              </td>

              <td
                v-if="isColVisible('created_at')"
                class="px-5 py-3.5 tabular-nums text-xs text-slate-500"
              >
                {{ d.created_at ? date(d.created_at) : '—' }}
              </td>

              <td
                v-if="isColVisible('updated_at')"
                class="px-5 py-3.5 tabular-nums text-xs text-slate-500"
              >
                {{ d.updated_at ? datetime(d.updated_at) : '—' }}
              </td>

              <td class="px-5 py-3.5">
                <div class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                  <button
                    v-if="d.can?.update"
                    class="grid h-7 w-7 place-items-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    title="Chỉnh sửa & nhân sự"
                    @click="open(d)"
                  >
                    <AppIcon
                      name="edit"
                      :size="14"
                    />
                  </button>
                  <button
                    v-if="d.can?.delete"
                    class="grid h-7 w-7 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                    title="Xoá"
                    @click="remove(d)"
                  >
                    <AppIcon
                      name="delete"
                      :size="14"
                    />
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!departments.data?.length">
              <td
                :colspan="totalCols"
                class="px-5 py-16 text-center"
              >
                <div class="flex flex-col items-center gap-2">
                  <AppIcon
                    name="department"
                    :size="36"
                    class="text-slate-200"
                  />
                  <p class="text-sm font-medium text-slate-400">
                    {{ hasAnyFilter ? 'Không có phòng ban phù hợp với bộ lọc.' : 'Chưa có phòng ban nào.' }}
                  </p>
                  <button
                    v-if="hasAnyFilter"
                    type="button"
                    class="mt-1 text-xs text-brand hover:underline"
                    @click="clearAll"
                  >
                    Xoá bộ lọc
                  </button>
                  <button
                    v-else-if="can.create"
                    class="btn-primary mt-2 gap-1 text-xs"
                    @click="open()"
                  >
                    <AppIcon
                      name="add"
                      :size="13"
                    /> Thêm phòng ban đầu tiên
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <DatagridPaginationFooter
        v-if="departments.meta"
        variant="bar"
        :meta="departments.meta"
        :per-page="perPage"
        :per-page-options="PER_PAGE_OPTIONS"
        @update:per-page="onPerPageChange"
      />

      <div
        v-if="departments.data?.length"
        class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-slate-100 bg-slate-50/40 px-5 py-2 text-xs text-slate-500"
      >
        <span>
          Tổng hệ thống:
          <span class="font-semibold text-slate-700">{{ summary.total }}</span> phòng ban
        </span>
        <span
          v-if="summary.active > 0"
          class="font-medium text-emerald-600"
        >{{ summary.active }} hoạt động</span>
        <span
          v-if="summary.inactive > 0"
          class="font-medium text-slate-500"
        >{{ summary.inactive }} ngừng</span>
      </div>
    </div>

    <DepartmentFormModal
      :show="modal"
      :department="editing"
      :employees="employees"
      :existing-departments="existingDepartments"
      @close="modal = false"
    />
  </AppLayout>
</template>
