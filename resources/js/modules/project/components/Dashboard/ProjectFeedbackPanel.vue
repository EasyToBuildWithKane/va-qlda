<script setup>
import { reactive, ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import FeedbackFormModal from '@/modules/project/components/FeedbackFormModal.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';

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

const FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái' },
    { key: 'category', label: 'Phân loại' },
    { key: 'mine', label: 'Tôi xử lý' },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS: filterControls,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.project-feedback.visible-filters');

const filterForm = reactive({
    status: '',
    category: '',
    mine: '',
    q: '',
});

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
    if (filterForm.mine === '1' && employeeId.value) {
        rows = rows.filter((f) => f.assignee?.id === employeeId.value || f.assignee_id === employeeId.value);
    }
    return rows;
});

const appliedFilterCount = computed(() =>
    [filterForm.status, filterForm.category, filterForm.mine].filter((v) => v !== '' && v != null).length,
);

function clearFilters() {
    filterForm.q = '';
    filterForm.status = '';
    filterForm.category = '';
    filterForm.mine = '';
}

function onToolbarClickOutside(e) {
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

const fullListHref = computed(() => `/feedback?project_id=${props.projectId}`);

function onSaved() {
    modal.value = false;
    router.reload({ only: ['feedbacks', 'feedbackSummary'], preserveScroll: true });
}
</script>

<template>
  <div class="w-full min-w-0 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="font-display text-lg font-semibold text-slate-800">
          Phản hồi dự án
        </h2>
        <p class="mt-0.5 text-sm text-slate-500">
          Cùng nguồn dữ liệu với
          <Link
            :href="fullListHref"
            class="font-medium text-brand hover:underline"
          >
            trang Phản hồi
          </Link>
          — đã lọc theo dự án này.
        </p>
      </div>
      <Link
        :href="fullListHref"
        class="inline-flex h-9 items-center gap-1.5 rounded-btn border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 hover:border-slate-300"
      >
        <AppIcon
          name="export"
          :size="14"
        />
        Mở danh sách đầy đủ
      </Link>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Đang xử lý
        </p>
        <p class="mt-1 font-display text-2xl font-bold text-sky-600">
          {{ summary.open ?? 0 }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Đã xử lý
        </p>
        <p class="mt-1 font-display text-2xl font-bold text-emerald-600">
          {{ summary.resolved ?? 0 }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-slate-500">
          Đánh giá TB
        </p>
        <p class="mt-1 font-display text-2xl font-bold text-amber-500">
          {{ summary.avg_rating ?? '—' }}
          <span
            v-if="summary.avg_rating"
            class="text-base"
          >★</span>
        </p>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="project-feedback-search"
              placeholder="Mã, tiêu đề, nội dung…"
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
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${filterControls.length})`"
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
                :controls="filterControls"
                @persist="persistVisibleFilters"
              />
            </div>
          </div>
          <button
            v-if="canCreate"
            type="button"
            class="btn-primary h-9 shrink-0 gap-1.5 px-4 text-sm"
            @click="modal = true"
          >
            <AppIcon
              name="add"
              :size="15"
            />
            Ghi phản hồi
          </button>
        </div>
      </div>

      <div
        v-if="hasFilterRow"
        class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-3 sm:px-5"
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
            v-for="o in statusOptions"
            :key="o.value"
            :value="o.value"
          >
            {{ o.label }}
          </option>
        </select>
        <select
          v-if="visibleFilters.category"
          v-model="filterForm.category"
          class="input h-9 w-44 text-sm"
          aria-label="Phân loại"
        >
          <option value="">
            Phân loại: Tất cả
          </option>
          <option
            v-for="o in categoryOptions"
            :key="o.value"
            :value="o.value"
          >
            {{ o.label }}
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
          v-if="appliedFilterCount || filterForm.q"
          type="button"
          class="text-xs font-medium text-brand hover:underline"
          @click="clearFilters"
        >
          Đặt lại
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
          <thead class="bg-slate-50 text-left text-[10px] font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-4 py-2.5 font-medium">
                Mã
              </th>
              <th class="px-4 py-2.5 font-medium">
                Tiêu đề
              </th>
              <th class="px-4 py-2.5 font-medium">
                Loại
              </th>
              <th class="px-4 py-2.5 font-medium">
                Người gửi
              </th>
              <th class="px-4 py-2.5 font-medium">
                Trạng thái
              </th>
              <th class="px-4 py-2.5 font-medium">
                Xử lý
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="f in filteredFeedbacks"
              :key="f.id"
              class="transition hover:bg-slate-50/80"
            >
              <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-brand">
                {{ f.code }}
              </td>
              <td class="max-w-xs px-4 py-2.5">
                <Link
                  :href="`/feedback/${f.id}`"
                  class="font-medium text-slate-800 hover:text-brand"
                >
                  {{ f.title }}
                </Link>
                <p
                  v-if="f.rating"
                  class="mt-0.5 text-xs text-amber-600"
                >
                  {{ f.rating }} ★
                </p>
              </td>
              <td class="px-4 py-2.5">
                <Badge
                  :label="f.category.label"
                  :color="f.category.color"
                />
              </td>
              <td class="px-4 py-2.5 text-xs text-slate-600">
                {{ f.reporter_display }}
              </td>
              <td class="px-4 py-2.5">
                <Badge
                  :label="f.status.label"
                  :color="f.status.color"
                />
              </td>
              <td class="px-4 py-2.5">
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
            </tr>
            <tr v-if="!filteredFeedbacks.length">
              <td
                colspan="6"
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
