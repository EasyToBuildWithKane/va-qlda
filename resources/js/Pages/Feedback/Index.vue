<script setup>
import { reactive, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import FeedbackFormModal from '@/modules/project/components/FeedbackFormModal.vue';
import FeedbackSummaryBar from '@/modules/project/components/FeedbackSummaryBar.vue';
import FeedbackListRowActions from '@/modules/project/components/FeedbackListRowActions.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { useDialog } from '@/composables/useDialog';
import { date, datetime } from '@/composables/useFormat';

const PER_PAGE_OPTIONS = [10, 15, 20, 30];

const props = defineProps({
    feedback: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const modal = ref(false);
const filterPanelDdRef = ref(null);
const colDdRef = ref(null);
const perPage = ref(Number(props.filters.per_page) || props.feedback.meta?.per_page || 20);

const FEEDBACK_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'category', label: 'Phân loại', default: false },
    { key: 'project', label: 'Dự án', default: false },
    { key: 'priority', label: 'Ưu tiên', default: false },
    { key: 'assignee', label: 'Người xử lý', default: false },
    { key: 'rating', label: 'Đánh giá', default: false },
    { key: 'mine', label: 'Tôi xử lý', default: false },
    { key: 'date_range', label: 'Thời gian', default: false },
];

const FEEDBACK_TABLE_COLUMNS = [
    { key: 'code', label: 'Mã' },
    { key: 'title', label: 'Tiêu đề' },
    { key: 'project', label: 'Dự án', default: false },
    { key: 'category', label: 'Phân loại' },
    { key: 'priority', label: 'Ưu tiên', default: false },
    { key: 'rating', label: 'Đánh giá', default: false },
    { key: 'reporter', label: 'Người gửi' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'assignee', label: 'Người xử lý' },
    { key: 'comments_count', label: 'Bình luận', default: false },
    { key: 'created_at', label: 'Ngày tạo', default: false },
    { key: 'updated_at', label: 'Cập nhật', default: false },
    { key: 'resolved_at', label: 'Ngày xử lý xong', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';
const RATING_FILTER_OPTIONS = [1, 2, 3, 4, 5];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(FEEDBACK_FILTER_CONTROLS, 'va-qlda.feedback.visible-filters.v3');

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(FEEDBACK_TABLE_COLUMNS, 'va-qlda.feedback.columns.v1');

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

const filterForm = reactive({
    status: props.filters.status ?? '',
    category: props.filters.category ?? '',
    project_id: props.filters.project_id ?? '',
    priority: props.filters.priority ?? '',
    assignee_id: props.filters.assignee_id ?? '',
    rating: props.filters.rating ?? '',
    mine: props.filters.mine ? '1' : '',
    scope: props.filters.scope ?? '',
    q: props.filters.q ?? '',
    created_from: props.filters.created_from ?? '',
    created_to: props.filters.created_to ?? '',
});

const appliedFilterCount = computed(() =>
    [
        filterForm.status,
        filterForm.category,
        filterForm.project_id,
        filterForm.priority,
        filterForm.assignee_id,
        filterForm.rating,
        filterForm.mine,
        filterForm.scope,
        filterForm.created_from,
        filterForm.created_to,
    ].filter((v) => v !== '' && v != null).length,
);

const tableColspan = computed(() =>
    TABLE_COLUMNS.filter((c) => isColVisible(c.key)).length + 1,
);

function feedbackRouteParams(resetPage = false) {
    const params = {
        status: filterForm.status || undefined,
        category: filterForm.category || undefined,
        project_id: filterForm.project_id || undefined,
        priority: filterForm.priority || undefined,
        assignee_id: filterForm.assignee_id || undefined,
        rating: filterForm.rating || undefined,
        mine: filterForm.mine || undefined,
        scope: filterForm.scope || undefined,
        q: filterForm.q || undefined,
        created_from: filterForm.created_from || undefined,
        created_to: filterForm.created_to || undefined,
        per_page: perPage.value,
    };
    if (resetPage) params.page = 1;
    return params;
}

function navigate(resetPage = false) {
    router.get('/feedback', feedbackRouteParams(resetPage), { preserveState: true, replace: true });
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => navigate(true), 350);
});

watch(
    () => [
        filterForm.status,
        filterForm.category,
        filterForm.project_id,
        filterForm.priority,
        filterForm.assignee_id,
        filterForm.rating,
        filterForm.mine,
        filterForm.scope,
        filterForm.created_from,
        filterForm.created_to,
    ],
    () => navigate(true),
);

watch(perPage, () => navigate(true));

function clearFilters() {
    filterForm.q = '';
    filterForm.status = '';
    filterForm.category = '';
    filterForm.project_id = '';
    filterForm.priority = '';
    filterForm.assignee_id = '';
    filterForm.rating = '';
    filterForm.mine = '';
    filterForm.scope = '';
    filterForm.created_from = '';
    filterForm.created_to = '';
}

function onQuickFilter({ scope, status }) {
    filterForm.scope = scope ?? '';
    filterForm.status = status ?? '';
}

const remove = async (f) => {
    if (!f.can?.delete) return;
    const ok = await dialog.confirm({
        title: 'Xoá phản hồi',
        message: `Xoá phản hồi «${f.title}» (${f.code})? Hành động không thể hoàn tác.`,
        tone: 'danger',
        confirmText: 'Xoá',
    });
    if (ok) {
        router.delete(`/feedback/${f.id}`, { preserveScroll: true });
    }
};
</script>

<template>
  <Head title="Phản hồi" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Theo dõi phản hồi"
        subtitle="Quản lý ý kiến và phản hồi người dùng"
        icon="feedback"
        icon-color="amber"
        :badge="summary.open ?? null"
      >
        <button
          v-if="can.create"
          type="button"
          class="btn-primary h-9 shrink-0 gap-1.5 px-3 text-sm"
          @click="modal = true"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Thêm phản hồi
        </button>
      </PageHeader>
    </template>

    <FeedbackSummaryBar
      :summary="summary"
      :active-scope="filterForm.scope"
      :active-status="filterForm.status"
      @quick-filter="onQuickFilter"
    />

    <div class="card overflow-visible">
      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="feedback-search"
              input-name="q"
              placeholder="Tiêu đề, mã hoặc nội dung phản hồi…"
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
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
                @click="openFilterPanel(() => { showColDd = false; })"
              >
                Lọc
              </DatagridToolbarActionButton>
              <FilterVisibilityDropdown
                v-model="visibleFilters"
                :show="showFilterPanelDd"
                :anchor-ref="filterPanelDdRef"
                :controls="FILTER_CONTROLS"
                input-id-prefix="feedback-filter-vis"
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
                :fixed-labels="['Thao tác']"
                input-id-prefix="feedback-col-vis"
                @persist="persistVisibleColumns"
              />
            </div>
          </div>
        </div>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
          >
            <DatagridFilterField v-if="visibleFilters.status">
              <label
                for="feedback-filter-status"
                class="sr-only"
              >Trạng thái</label>
              <select
                id="feedback-filter-status"
                v-model="filterForm.status"
                name="status"
                :class="FILTER_CONTROL_CLASS"
                @change="filterForm.scope = ''"
              >
                <option value="">
                  Trạng thái
                </option>
                <option
                  v-for="o in options.status"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.category">
              <label
                for="feedback-filter-category"
                class="sr-only"
              >Phân loại</label>
              <select
                id="feedback-filter-category"
                v-model="filterForm.category"
                name="category"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Phân loại
                </option>
                <option
                  v-for="o in options.category"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.project">
              <label
                for="feedback-filter-project"
                class="sr-only"
              >Dự án</label>
              <select
                id="feedback-filter-project"
                v-model="filterForm.project_id"
                name="project_id"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Dự án
                </option>
                <option
                  v-for="p in options.projects"
                  :key="p.id"
                  :value="p.id"
                >
                  {{ p.name }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.priority">
              <label
                for="feedback-filter-priority"
                class="sr-only"
              >Ưu tiên</label>
              <select
                id="feedback-filter-priority"
                v-model="filterForm.priority"
                name="priority"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Ưu tiên
                </option>
                <option
                  v-for="o in options.priority"
                  :key="o.value"
                  :value="o.value"
                >
                  {{ o.label }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.assignee">
              <label
                for="feedback-filter-assignee"
                class="sr-only"
              >Người xử lý</label>
              <select
                id="feedback-filter-assignee"
                v-model="filterForm.assignee_id"
                name="assignee_id"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Người xử lý
                </option>
                <option
                  v-for="e in options.employees"
                  :key="e.id"
                  :value="e.id"
                >
                  {{ e.name }}
                </option>
              </select>
            </DatagridFilterField>

            <DatagridFilterField v-if="visibleFilters.rating">
              <label
                for="feedback-filter-rating"
                class="sr-only"
              >Đánh giá</label>
              <select
                id="feedback-filter-rating"
                v-model="filterForm.rating"
                name="rating"
                :class="FILTER_CONTROL_CLASS"
              >
                <option value="">
                  Đánh giá
                </option>
                <option
                  v-for="n in RATING_FILTER_OPTIONS"
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
              <label
                for="feedback-filter-mine"
                class="flex h-10 w-full cursor-pointer items-center gap-2 rounded-btn border border-slate-200 px-3 text-sm text-slate-600"
              >
                <input
                  id="feedback-filter-mine"
                  v-model="filterForm.mine"
                  name="mine"
                  true-value="1"
                  false-value=""
                  type="checkbox"
                  class="rounded border-slate-300 text-brand focus:ring-brand/30"
                >
                Tôi xử lý
              </label>
            </DatagridFilterField>

            <div
              v-if="visibleFilters.date_range"
              class="min-w-0 w-full sm:col-span-2 xl:col-span-2"
            >
              <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                <div>
                  <label
                    for="feedback-filter-created-from"
                    class="sr-only"
                  >Từ ngày</label>
                  <FilterDatePicker
                    id="feedback-filter-created-from"
                    v-model="filterForm.created_from"
                    name="created_from"
                    placeholder="Từ ngày"
                    :max-date="filterForm.created_to || null"
                  />
                </div>
                <div>
                  <label
                    for="feedback-filter-created-to"
                    class="sr-only"
                  >Đến ngày</label>
                  <FilterDatePicker
                    id="feedback-filter-created-to"
                    v-model="filterForm.created_to"
                    name="created_to"
                    placeholder="Đến ngày"
                    :min-date="filterForm.created_from || null"
                  />
                </div>
              </div>
            </div>

            <div
              v-if="appliedFilterCount || filterForm.q"
              class="col-span-full flex justify-end"
            >
              <button
                type="button"
                class="text-xs font-medium text-brand hover:underline"
                @click="clearFilters"
              >
                Đặt lại bộ lọc
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
          <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
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
                v-if="isColVisible('project')"
                class="px-4 py-2.5 font-medium"
              >
                Dự án
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
                v-if="isColVisible('comments_count')"
                class="px-4 py-2.5 font-medium text-center"
              >
                BL
              </th>
              <th
                v-if="isColVisible('created_at')"
                class="px-4 py-2.5 font-medium"
              >
                Ngày tạo
              </th>
              <th
                v-if="isColVisible('updated_at')"
                class="px-4 py-2.5 font-medium"
              >
                Cập nhật
              </th>
              <th
                v-if="isColVisible('resolved_at')"
                class="px-4 py-2.5 font-medium"
              >
                Ngày xử lý xong
              </th>
              <th
                class="w-11 px-2 py-2.5 text-right font-medium"
                aria-label="Thao tác"
              >
                <span class="sr-only">Thao tác</span>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="f in feedback.data"
              :key="f.id"
              class="transition-colors hover:bg-slate-50/80"
            >
              <td
                v-if="isColVisible('code')"
                class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-slate-500"
              >
                {{ f.code }}
              </td>
              <td
                v-if="isColVisible('title')"
                class="max-w-xs px-4 py-2.5"
              >
                <Link
                  :href="`/feedback/${f.id}`"
                  class="font-medium text-slate-700 hover:text-brand"
                >
                  {{ f.title }}
                </Link>
                <p
                  v-if="f.project?.name && !isColVisible('project')"
                  class="mt-0.5 text-[11px] text-slate-400"
                >
                  {{ f.project.name }}
                </p>
              </td>
              <td
                v-if="isColVisible('project')"
                class="px-4 py-2.5 text-slate-600"
              >
                <Link
                  v-if="f.project"
                  :href="`/projects/${f.project.id}?tab=feedback`"
                  class="text-sm hover:text-brand"
                >
                  {{ f.project.name }}
                </Link>
                <span
                  v-else
                  class="text-slate-400"
                >—</span>
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
                  class="text-slate-400"
                >—</span>
              </td>
              <td
                v-if="isColVisible('rating')"
                class="px-4 py-2.5 text-amber-600"
              >
                {{ f.rating ? `${f.rating} ★` : '—' }}
              </td>
              <td
                v-if="isColVisible('reporter')"
                class="px-4 py-2.5 text-slate-500"
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
                  class="flex items-center gap-1.5"
                >
                  <Avatar
                    :name="f.assignee.name"
                    :src="f.assignee.avatar_path"
                    :size="22"
                  />
                  <span class="text-slate-600">{{ f.assignee.name }}</span>
                </div>
                <span
                  v-else
                  class="text-slate-400"
                >—</span>
              </td>
              <td
                v-if="isColVisible('comments_count')"
                class="px-4 py-2.5 text-center tabular-nums text-slate-600"
              >
                {{ f.comments_count ?? 0 }}
              </td>
              <td
                v-if="isColVisible('created_at')"
                class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-600"
              >
                {{ f.created_at ? date(f.created_at) : '—' }}
              </td>
              <td
                v-if="isColVisible('updated_at')"
                class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-600"
              >
                {{ f.updated_at ? datetime(f.updated_at) : '—' }}
              </td>
              <td
                v-if="isColVisible('resolved_at')"
                class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-600"
              >
                {{ f.resolved_at ? datetime(f.resolved_at) : '—' }}
              </td>
              <td class="px-2 py-2.5 text-right">
                <FeedbackListRowActions
                  :feedback="f"
                  @remove="remove"
                />
              </td>
            </tr>
            <tr v-if="!feedback.data.length">
              <td
                :colspan="Math.max(tableColspan, 2)"
                class="px-4 py-12 text-center text-slate-400"
              >
                Không có phản hồi phù hợp bộ lọc.
              </td>
            </tr>
          </tbody>
          <DatagridPaginationFooter
            v-if="feedback.meta"
            :meta="feedback.meta"
            :per-page="perPage"
            :per-page-options="PER_PAGE_OPTIONS"
            :colspan="tableColspan"
            @update:per-page="perPage = $event"
          />
        </table>
      </div>
    </div>

    <FeedbackFormModal
      :show="modal"
      :projects="options.projects"
      :employees="options.employees"
      :category-options="options.category"
      :status-options="options.status"
      :priority-options="options.priority"
      @close="modal = false"
    />
  </AppLayout>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
