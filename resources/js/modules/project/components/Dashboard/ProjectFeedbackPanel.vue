<script setup>
import { reactive, ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import FeedbackFormModal from '@/modules/project/components/FeedbackFormModal.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { date, datetime } from '@/composables/useFormat';

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const props = defineProps({
    projectId: { type: Number, required: true },
    projectCode: { type: String, default: '' },
    projectName: { type: String, default: '' },
    feedbacks: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    employees: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    canCreate: { type: Boolean, default: false },
});

const page = usePage();
const modal = ref(false);
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);

const FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'category', label: 'Phân loại', default: false },
    { key: 'priority', label: 'Ưu tiên', default: false },
    { key: 'assignee', label: 'Người xử lý', default: false },
    { key: 'rating', label: 'Đánh giá', default: false },
    { key: 'mine', label: 'Tôi xử lý', default: false },
];

const FEEDBACK_TABLE_COLUMNS = [
    { key: 'code', label: 'Mã' },
    { key: 'title', label: 'Tiêu đề' },
    { key: 'category', label: 'Phân loại' },
    { key: 'priority', label: 'Ưu tiên', default: false },
    { key: 'rating', label: 'Đánh giá', default: false },
    { key: 'reporter', label: 'Người gửi' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'assignee', label: 'Người xử lý' },
    { key: 'created_at', label: 'Ngày tạo', default: false },
    { key: 'resolved_at', label: 'Ngày xử lý xong', default: false },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS: filterControls,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.project-feedback.visible-filters.v3');

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(FEEDBACK_TABLE_COLUMNS, 'va-qlda.project-feedback.columns');

const filterForm = reactive({
    status: '',
    category: '',
    priority: '',
    assignee_id: '',
    rating: '',
    mine: '',
    q: '',
});

const ratingFilterOptions = [1, 2, 3, 4, 5];

const tableColspan = computed(() =>
    TABLE_COLUMNS.filter((c) => isColVisible(c.key)).length,
);

const employeeId = computed(() => page.props.auth?.user?.employee_id ?? null);

const filteredFeedbacks = computed(() => {
    let rows = props.feedbacks ?? [];
    const q = filterForm.q.trim().toLowerCase();
    if (q) {
        rows = rows.filter((f) =>
            (f.title ?? '').toLowerCase().includes(q)
            || (f.code ?? '').toLowerCase().includes(q)
            || (f.description ?? '').toLowerCase().includes(q));
    }
    if (filterForm.status) {
        rows = rows.filter((f) => f.status?.value === filterForm.status);
    }
    if (filterForm.category) {
        rows = rows.filter((f) => f.category?.value === filterForm.category);
    }
    if (filterForm.priority) {
        rows = rows.filter((f) => f.priority?.value === filterForm.priority);
    }
    if (filterForm.assignee_id) {
        const aid = Number(filterForm.assignee_id);
        rows = rows.filter((f) => f.assignee?.id === aid || f.assignee_id === aid);
    }
    if (filterForm.rating) {
        const r = Number(filterForm.rating);
        rows = rows.filter((f) => f.rating === r);
    }
    if (filterForm.mine === '1' && employeeId.value) {
        rows = rows.filter((f) => f.assignee?.id === employeeId.value || f.assignee_id === employeeId.value);
    }
    return rows;
});

const appliedFilterCount = computed(() =>
    [
        filterForm.status,
        filterForm.category,
        filterForm.priority,
        filterForm.assignee_id,
        filterForm.rating,
        filterForm.mine,
    ].filter((v) => v !== '' && v != null).length,
);

function clearFilters() {
    filterForm.q = '';
    filterForm.status = '';
    filterForm.category = '';
    filterForm.priority = '';
    filterForm.assignee_id = '';
    filterForm.rating = '';
    filterForm.mine = '';
}

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (e.target.closest?.('[data-column-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(e.target)) {
        showColDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function onSaved() {
    modal.value = false;
    router.reload({ only: ['feedbacks', 'feedbackSummary'], preserveScroll: true });
}

function openCreate() {
    modal.value = true;
}

defineExpose({ openCreate });
</script>

<template>
  <div class="flex h-full min-h-0 w-full min-w-0 flex-col">
    <div class="card flex min-h-0 flex-1 flex-col overflow-hidden">
      <div class="shrink-0 overflow-visible border-b border-slate-100 px-5 py-4 dark:border-slate-700">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="project-feedback-search"
              placeholder="Mã, tiêu đề, nội dung…"
              stretch
              inline-actions
              hide-label
              input-height="h-10"
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
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${filterControls.length})`"
                @click="openFilterPanel(() => { showColDd = false; })"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="filterControls"
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
                @click="openColPanel(() => { showFilterPanelDd = false; })"
              >
                Cột
              </DatagridToolbarActionButton>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :columns="TABLE_COLUMNS"
                :anchor-ref="colDdRef"
                @persist="persistVisibleColumns"
              />
            </div>
          </div>
        </div>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 dark:border-slate-700"
          >
            <DatagridFilterField v-if="visibleFilters.status">
              <select
                v-model="filterForm.status"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Trạng thái"
              >
                <option value="">
                  Trạng thái
                </option>
                <option
                  v-for="o in statusOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.category">
              <select
                v-model="filterForm.category"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Phân loại"
              >
                <option value="">
                  Phân loại
                </option>
                <option
                  v-for="o in categoryOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.priority">
              <select
                v-model="filterForm.priority"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Ưu tiên"
              >
                <option value="">
                  Ưu tiên
                </option>
                <option
                  v-for="o in priorityOptions"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.assignee">
              <select
                v-model="filterForm.assignee_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Người xử lý"
              >
                <option value="">
                  Người xử lý
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

            <DatagridFilterField v-if="visibleFilters.rating">
              <select
                v-model="filterForm.rating"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Đánh giá"
              >
                <option value="">
                  Đánh giá
                </option>
                <option
                  v-for="n in ratingFilterOptions"
                  :key="n"
                  :value="n"
                >
                  {{ n }} ★
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField
              v-if="visibleFilters.mine"
              class="flex items-center"
            >
              <label class="flex h-10 w-full cursor-pointer items-center gap-2 rounded-btn border border-slate-200 px-3 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300">
                <input
                  v-model="filterForm.mine"
                  true-value="1"
                  false-value=""
                  type="checkbox"
                  class="rounded border-slate-300 text-brand focus:ring-brand/30"
                >
                Tôi xử lý
              </label>
            </DatagridFilterField>

            <div
              v-if="appliedFilterCount || filterForm.q"
              class="col-span-full flex justify-end"
            >
              <button
                type="button"
                class="text-xs font-medium text-brand"
                @click="clearFilters"
              >
                Đặt lại bộ lọc
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <div class="min-h-0 flex-1 overflow-x-auto overflow-y-auto">
        <table class="w-full min-w-[640px] text-sm">
          <thead class="sticky top-0 z-10 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th
                v-if="isColVisible('code')"
                class="px-4 py-2.5 font-medium"
              >
                Mã
              </th>
              <th
                v-if="isColVisible('title')"
                class="px-4 py-2.5 font-medium"
              >
                Tiêu đề
              </th>
              <th
                v-if="isColVisible('category')"
                class="px-4 py-2.5 font-medium"
              >
                Phân loại
              </th>
              <th
                v-if="isColVisible('priority')"
                class="px-4 py-2.5 font-medium"
              >
                Ưu tiên
              </th>
              <th
                v-if="isColVisible('rating')"
                class="px-4 py-2.5 font-medium"
              >
                Đánh giá
              </th>
              <th
                v-if="isColVisible('reporter')"
                class="px-4 py-2.5 font-medium"
              >
                Người gửi
              </th>
              <th
                v-if="isColVisible('status')"
                class="px-4 py-2.5 font-medium"
              >
                Trạng thái
              </th>
              <th
                v-if="isColVisible('assignee')"
                class="px-4 py-2.5 font-medium"
              >
                Người xử lý
              </th>
              <th
                v-if="isColVisible('created_at')"
                class="px-4 py-2.5 font-medium"
              >
                Ngày tạo
              </th>
              <th
                v-if="isColVisible('resolved_at')"
                class="px-4 py-2.5 font-medium"
              >
                Ngày xử lý xong
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="f in filteredFeedbacks"
              :key="f.id"
              class="transition hover:bg-slate-50/80"
            >
              <td
                v-if="isColVisible('code')"
                class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-brand"
              >
                {{ f.code }}
              </td>
              <td
                v-if="isColVisible('title')"
                class="max-w-xs px-4 py-2.5"
              >
                <Link
                  :href="`/feedback/${f.id}`"
                  class="font-medium text-slate-800 hover:text-brand"
                >
                  {{ f.title }}
                </Link>
                <p
                  v-if="f.rating && !isColVisible('rating')"
                  class="mt-0.5 text-xs text-amber-600"
                >
                  {{ f.rating }} ★
                </p>
              </td>
              <td
                v-if="isColVisible('category')"
                class="px-4 py-2.5"
              >
                <Badge
                  :label="f.category.label"
                  :color="f.category.color"
                />
              </td>
              <td
                v-if="isColVisible('priority')"
                class="px-4 py-2.5"
              >
                <Badge
                  v-if="f.priority"
                  :label="f.priority.label"
                  :color="f.priority.color"
                />
                <span
                  v-else
                  class="text-xs text-slate-400"
                >—</span>
              </td>
              <td
                v-if="isColVisible('rating')"
                class="px-4 py-2.5 text-sm text-amber-600"
              >
                {{ f.rating ? `${f.rating} ★` : '—' }}
              </td>
              <td
                v-if="isColVisible('reporter')"
                class="px-4 py-2.5 text-xs text-slate-600"
              >
                {{ f.reporter_display }}
              </td>
              <td
                v-if="isColVisible('status')"
                class="px-4 py-2.5"
              >
                <Badge
                  :label="f.status.label"
                  :color="f.status.color"
                />
              </td>
              <td
                v-if="isColVisible('assignee')"
                class="px-4 py-2.5"
              >
                <div
                  v-if="f.assignee"
                  class="flex min-w-0 items-center gap-1.5"
                >
                  <Avatar
                    :name="f.assignee.name"
                    :src="f.assignee.avatar_path"
                    :size="22"
                  />
                  <span class="truncate text-xs text-slate-700">{{ f.assignee.name }}</span>
                </div>
                <span
                  v-else
                  class="text-xs text-slate-400"
                >—</span>
              </td>
              <td
                v-if="isColVisible('created_at')"
                class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-600"
              >
                {{ f.created_at ? date(f.created_at) : '—' }}
              </td>
              <td
                v-if="isColVisible('resolved_at')"
                class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-600"
              >
                {{ f.resolved_at ? datetime(f.resolved_at) : '—' }}
              </td>
            </tr>
            <tr v-if="!filteredFeedbacks.length">
              <td
                :colspan="Math.max(tableColspan, 1)"
                class="px-4 py-12 text-center text-sm text-slate-400"
              >
                {{ feedbacks.length ? 'Không có phản hồi phù hợp bộ lọc.' : 'Chưa có phản hồi cho dự án này.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <FeedbackFormModal
      :show="modal"
      :projects="[{ id: projectId, name: projectName, code: projectCode }]"
      :employees="employees"
      :category-options="categoryOptions"
      :status-options="statusOptions"
      :priority-options="priorityOptions"
      :default-project-id="projectId"
      :lock-project="true"
      return-to="project"
      @close="modal = false"
      @saved="onSaved"
    />
  </div>
</template>
