<script setup>
import { reactive, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import BlockerFormModal from '@/modules/project/components/BlockerFormModal.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import ColumnVisibilityDropdown from '@/shared/ui/ColumnVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useVisibleColumns } from '@/shared/composables/useVisibleColumns';
import { date, datetime } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';

const PER_PAGE_OPTIONS = [5, 10, 15, 20];

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
const colDdRef = ref(null);
const perPage = ref(Number(props.filters.per_page) || props.blockers.meta?.per_page || 10);

const BLOCKER_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái' },
    { key: 'severity', label: 'Mức độ' },
    { key: 'project', label: 'Dự án' },
    { key: 'owner', label: 'Người xử lý' },
    { key: 'raised_by', label: 'Người báo' },
    { key: 'overdue', label: 'Quá hạn' },
    { key: 'mine', label: 'Tôi xử lý' },
];

const BLOCKER_TABLE_COLUMNS = [
    { key: 'code', label: 'Mã' },
    { key: 'title', label: 'Tiêu đề' },
    { key: 'project', label: 'Dự án' },
    { key: 'task', label: 'Công việc' },
    { key: 'severity', label: 'Mức độ' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'raised_by', label: 'Người báo' },
    { key: 'owner', label: 'Người xử lý' },
    { key: 'raised_at', label: 'Ngày báo' },
    { key: 'due_date', label: 'Hạn xử lý' },
    { key: 'resolved_at', label: 'Ngày xử lý xong' },
    { key: 'comments', label: 'Bình luận' },
    { key: 'description', label: 'Mô tả' },
    { key: 'root_cause', label: 'Nguyên nhân' },
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

const {
    visibleCols,
    showColDd,
    persistVisibleColumns,
    openColPanel,
    isColVisible,
    TABLE_COLUMNS,
} = useVisibleColumns(BLOCKER_TABLE_COLUMNS, 'va-qlda.blockers.columns');

const open = (b = null) => { editing.value = b; modal.value = true; };

const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    severity: props.filters.severity ?? '',
    project_id: props.filters.project_id ?? '',
    owner_id: props.filters.owner_id ?? '',
    raised_by_id: props.filters.raised_by_id ?? '',
    mine: props.filters.mine ? '1' : '',
    overdue: props.filters.overdue ? '1' : '',
});

const appliedFilterCount = computed(() =>
    [
        filterForm.status,
        filterForm.severity,
        filterForm.project_id,
        filterForm.owner_id,
        filterForm.raised_by_id,
        filterForm.mine,
        filterForm.overdue,
    ].filter((v) => v !== '' && v != null).length,
);

const tableColspan = computed(() => {
    let n = TABLE_COLUMNS.filter((c) => isColVisible(c.key)).length + 1;
    return n;
});

function routeParams() {
    return {
        q: filterForm.q || undefined,
        status: filterForm.status || undefined,
        severity: filterForm.severity || undefined,
        project_id: filterForm.project_id || undefined,
        owner_id: filterForm.owner_id || undefined,
        raised_by_id: filterForm.raised_by_id || undefined,
        mine: filterForm.mine || undefined,
        overdue: filterForm.overdue || undefined,
        per_page: perPage.value,
    };
}

function reloadBlockers() {
    router.get('/blockers', routeParams(), { preserveState: true, replace: true, preserveScroll: true });
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(reloadBlockers, 350);
});

watch(
    () => [
        filterForm.status,
        filterForm.severity,
        filterForm.project_id,
        filterForm.owner_id,
        filterForm.raised_by_id,
        filterForm.mine,
        filterForm.overdue,
    ],
    reloadBlockers,
);

function onPerPageChange(n) {
    perPage.value = n;
    reloadBlockers();
}

function clearFilters() {
    filterForm.q = '';
    filterForm.status = '';
    filterForm.severity = '';
    filterForm.project_id = '';
    filterForm.owner_id = '';
    filterForm.raised_by_id = '';
    filterForm.mine = '';
    filterForm.overdue = '';
    reloadBlockers();
}

function onToolbarClickOutside(e) {
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
    if (colDdRef.value && !colDdRef.value.contains(e.target)) {
        showColDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

const resolve = (b) => router.put(`/blockers/${b.id}`, { status: 'resolved' }, { preserveScroll: true });
const remove = async (b) => {
    if (await dialog.confirm({ title: 'Xoá vướng mắc', message: `Xoá "${b.title}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/blockers/${b.id}`, { preserveScroll: true });
};

function truncate(text, max = 80) {
    if (!text) return '—';
    return text.length > max ? `${text.slice(0, max)}…` : text;
}
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

    <div class="mb-4 grid grid-cols-3 gap-3">
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Đang mở
        </p>
        <p class="mt-1 font-display text-2xl font-bold text-amber-600">
          {{ summary.open ?? 0 }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Nghiêm trọng
        </p>
        <p class="mt-1 font-display text-2xl font-bold text-rose-600">
          {{ summary.critical ?? 0 }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Đã giải quyết
        </p>
        <p class="mt-1 font-display text-2xl font-bold text-emerald-600">
          {{ summary.resolved ?? 0 }}
        </p>
      </div>
    </div>

    <div class="card overflow-visible">
      <div class="border-b border-slate-100 px-5 py-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="blockers-search"
              placeholder="Mã, tiêu đề, mô tả, nguyên nhân…"
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
                @click="openFilterPanel(() => { showColDd = false; })"
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
            <div
              ref="colDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition"
                :class="showColDd
                  ? 'border-brand/40 bg-brand/5 text-brand'
                  : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
                title="Cột hiển thị"
                @click="openColPanel(() => { showFilterPanelDd = false; })"
              >
                <AppIcon
                  name="columns"
                  :size="15"
                />
                <span>Cột</span>
              </button>
              <ColumnVisibilityDropdown
                v-model="visibleCols"
                :show="showColDd"
                :columns="TABLE_COLUMNS"
                :fixed-labels="['Thao tác']"
                @persist="persistVisibleColumns"
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
        class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3"
      >
        <select
          v-if="visibleFilters.status"
          v-model="filterForm.status"
          class="input h-9 w-44 text-sm"
          aria-label="Trạng thái"
        >
          <option value="">
            Mặc định (chưa xử lý)
          </option>
          <option value="all">
            Tất cả trạng thái
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
        <select
          v-if="visibleFilters.owner"
          v-model="filterForm.owner_id"
          class="input h-9 min-w-[10rem] text-sm sm:w-48"
          aria-label="Người xử lý"
        >
          <option value="">
            Người xử lý: Tất cả
          </option>
          <option
            v-for="e in options.employees"
            :key="e.id"
            :value="e.id"
          >
            {{ e.name }}
          </option>
        </select>
        <select
          v-if="visibleFilters.raised_by"
          v-model="filterForm.raised_by_id"
          class="input h-9 min-w-[10rem] text-sm sm:w-48"
          aria-label="Người báo"
        >
          <option value="">
            Người báo: Tất cả
          </option>
          <option
            v-for="e in options.employees"
            :key="`r-${e.id}`"
            :value="e.id"
          >
            {{ e.name }}
          </option>
        </select>
        <label
          v-if="visibleFilters.overdue"
          class="inline-flex h-9 items-center gap-2 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600"
        >
          <input
            v-model="filterForm.overdue"
            true-value="1"
            false-value=""
            type="checkbox"
            class="rounded border-slate-300 text-brand"
          >
          Chỉ quá hạn
        </label>
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
          v-if="appliedFilterCount || filterForm.q"
          type="button"
          class="text-xs font-medium text-brand hover:underline"
          @click="clearFilters"
        >
          Đặt lại
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[960px] text-left text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th
                v-if="isColVisible('code')"
                class="whitespace-nowrap px-4 py-3"
              >
                Mã
              </th>
              <th
                v-if="isColVisible('title')"
                class="min-w-[12rem] px-4 py-3"
              >
                Tiêu đề
              </th>
              <th
                v-if="isColVisible('project')"
                class="px-4 py-3"
              >
                Dự án
              </th>
              <th
                v-if="isColVisible('task')"
                class="px-4 py-3"
              >
                Công việc
              </th>
              <th
                v-if="isColVisible('severity')"
                class="px-4 py-3"
              >
                Mức độ
              </th>
              <th
                v-if="isColVisible('status')"
                class="px-4 py-3"
              >
                Trạng thái
              </th>
              <th
                v-if="isColVisible('raised_by')"
                class="px-4 py-3"
              >
                Người báo
              </th>
              <th
                v-if="isColVisible('owner')"
                class="px-4 py-3"
              >
                Người xử lý
              </th>
              <th
                v-if="isColVisible('raised_at')"
                class="whitespace-nowrap px-4 py-3"
              >
                Ngày báo
              </th>
              <th
                v-if="isColVisible('due_date')"
                class="whitespace-nowrap px-4 py-3"
              >
                Hạn xử lý
              </th>
              <th
                v-if="isColVisible('resolved_at')"
                class="whitespace-nowrap px-4 py-3"
              >
                Ngày xử lý xong
              </th>
              <th
                v-if="isColVisible('comments')"
                class="px-4 py-3 text-center"
              >
                BL
              </th>
              <th
                v-if="isColVisible('description')"
                class="min-w-[10rem] px-4 py-3"
              >
                Mô tả
              </th>
              <th
                v-if="isColVisible('root_cause')"
                class="min-w-[10rem] px-4 py-3"
              >
                Nguyên nhân
              </th>
              <th class="whitespace-nowrap px-4 py-3 text-center">
                Thao tác
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="b in blockers.data"
              :key="b.id"
              class="hover:bg-slate-50/80"
              :class="b.is_overdue ? 'bg-rose-50/30' : ''"
            >
              <td
                v-if="isColVisible('code')"
                class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-brand"
              >
                {{ b.code }}
              </td>
              <td
                v-if="isColVisible('title')"
                class="px-4 py-3"
              >
                <span class="font-medium text-slate-800">{{ b.title }}</span>
              </td>
              <td
                v-if="isColVisible('project')"
                class="px-4 py-3 text-slate-600"
              >
                {{ b.project?.name ?? '—' }}
              </td>
              <td
                v-if="isColVisible('task')"
                class="max-w-[10rem] truncate px-4 py-3 text-slate-600"
                :title="b.task?.title"
              >
                {{ b.task?.title ?? '—' }}
              </td>
              <td
                v-if="isColVisible('severity')"
                class="px-4 py-3"
              >
                <Badge
                  :label="b.severity.label"
                  :color="b.severity.color"
                />
              </td>
              <td
                v-if="isColVisible('status')"
                class="px-4 py-3"
              >
                <Badge
                  :label="b.status.label"
                  :color="b.status.color"
                />
              </td>
              <td
                v-if="isColVisible('raised_by')"
                class="px-4 py-3"
              >
                <div
                  v-if="b.raised_by?.name"
                  class="flex items-center gap-1.5"
                >
                  <Avatar
                    :name="b.raised_by.name"
                    :src="b.raised_by.avatar_path"
                    :size="22"
                  />
                  <span class="text-slate-600">{{ b.raised_by.name }}</span>
                </div>
                <span
                  v-else
                  class="text-slate-400"
                >—</span>
              </td>
              <td
                v-if="isColVisible('owner')"
                class="px-4 py-3"
              >
                <div
                  v-if="b.owner?.name"
                  class="flex items-center gap-1.5"
                >
                  <Avatar
                    :name="b.owner.name"
                    :src="b.owner.avatar_path"
                    :size="22"
                  />
                  <span class="text-slate-600">{{ b.owner.name }}</span>
                </div>
                <span
                  v-else
                  class="text-slate-400"
                >—</span>
              </td>
              <td
                v-if="isColVisible('raised_at')"
                class="whitespace-nowrap px-4 py-3 text-xs text-slate-500"
              >
                {{ b.raised_at ? date(b.raised_at) : '—' }}
              </td>
              <td
                v-if="isColVisible('due_date')"
                class="whitespace-nowrap px-4 py-3 text-xs"
                :class="b.is_overdue ? 'font-medium text-rose-600' : 'text-slate-500'"
              >
                {{ b.due_date ? date(b.due_date) : '—' }}
              </td>
              <td
                v-if="isColVisible('resolved_at')"
                class="whitespace-nowrap px-4 py-3 text-xs text-slate-500"
              >
                {{ b.resolved_at ? datetime(b.resolved_at) : '—' }}
              </td>
              <td
                v-if="isColVisible('comments')"
                class="px-4 py-3 text-center text-xs tabular-nums text-slate-500"
              >
                {{ b.comments_count ?? 0 }}
              </td>
              <td
                v-if="isColVisible('description')"
                class="max-w-[14rem] px-4 py-3 text-xs text-slate-500"
                :title="b.description"
              >
                {{ truncate(b.description) }}
              </td>
              <td
                v-if="isColVisible('root_cause')"
                class="max-w-[14rem] px-4 py-3 text-xs text-slate-500"
                :title="b.root_cause"
              >
                {{ truncate(b.root_cause) }}
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-0.5">
                  <button
                    v-if="b.can?.update && b.status.value !== 'resolved'"
                    type="button"
                    class="grid h-8 w-8 place-items-center rounded-lg text-emerald-600 hover:bg-emerald-50"
                    title="Đã xử lý"
                    @click="resolve(b)"
                  >
                    <AppIcon
                      name="done"
                      :size="15"
                    />
                  </button>
                  <button
                    v-if="b.can?.update"
                    type="button"
                    class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-slate-100"
                    title="Sửa"
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
                    class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                    title="Xoá"
                    @click="remove(b)"
                  >
                    <AppIcon
                      name="delete"
                      :size="15"
                    />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!blockers.data?.length">
              <td
                :colspan="tableColspan"
                class="px-4 py-14 text-center text-sm text-slate-400"
              >
                Không có vướng mắc phù hợp bộ lọc.
              </td>
            </tr>
          </tbody>
          <tfoot class="border-t border-slate-200 bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th
                v-if="isColVisible('code')"
                class="whitespace-nowrap px-4 py-2"
              >
                Mã
              </th>
              <th
                v-if="isColVisible('title')"
                class="px-4 py-2"
              >
                Tiêu đề
              </th>
              <th
                v-if="isColVisible('project')"
                class="px-4 py-2"
              >
                Dự án
              </th>
              <th
                v-if="isColVisible('task')"
                class="px-4 py-2"
              >
                Công việc
              </th>
              <th
                v-if="isColVisible('severity')"
                class="px-4 py-2"
              >
                Mức độ
              </th>
              <th
                v-if="isColVisible('status')"
                class="px-4 py-2"
              >
                Trạng thái
              </th>
              <th
                v-if="isColVisible('raised_by')"
                class="px-4 py-2"
              >
                Người báo
              </th>
              <th
                v-if="isColVisible('owner')"
                class="px-4 py-2"
              >
                Người xử lý
              </th>
              <th
                v-if="isColVisible('raised_at')"
                class="px-4 py-2"
              >
                Ngày báo
              </th>
              <th
                v-if="isColVisible('due_date')"
                class="px-4 py-2"
              >
                Hạn xử lý
              </th>
              <th
                v-if="isColVisible('resolved_at')"
                class="px-4 py-2"
              >
                Ngày xử lý xong
              </th>
              <th
                v-if="isColVisible('comments')"
                class="px-4 py-2 text-center"
              >
                BL
              </th>
              <th
                v-if="isColVisible('description')"
                class="px-4 py-2"
              >
                Mô tả
              </th>
              <th
                v-if="isColVisible('root_cause')"
                class="px-4 py-2"
              >
                Nguyên nhân
              </th>
              <th class="px-4 py-2 text-center">
                Thao tác
              </th>
            </tr>
            <DatagridPaginationFooter
              :meta="blockers.meta"
              :per-page="perPage"
              :per-page-options="PER_PAGE_OPTIONS"
              :colspan="tableColspan"
              @update:per-page="onPerPageChange"
            />
          </tfoot>
        </table>
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
