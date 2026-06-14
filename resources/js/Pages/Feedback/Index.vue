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
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useDialog } from '@/composables/useDialog';

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
const perPage = ref(Number(props.filters.per_page) || props.feedback.meta?.per_page || 20);

const FEEDBACK_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'category', label: 'Phân loại', default: false },
    { key: 'project', label: 'Dự án', default: false },
    { key: 'mine', label: 'Tôi xử lý', default: false },
];

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(FEEDBACK_FILTER_CONTROLS, 'va-qlda.feedback.visible-filters.v2');

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

const filterForm = reactive({
    status: props.filters.status ?? '',
    category: props.filters.category ?? '',
    project_id: props.filters.project_id ?? '',
    mine: props.filters.mine ? '1' : '',
    scope: props.filters.scope ?? '',
    q: props.filters.q ?? '',
});

const appliedFilterCount = computed(() =>
    [filterForm.status, filterForm.category, filterForm.project_id, filterForm.mine, filterForm.scope]
        .filter((v) => v !== '' && v != null).length,
);

function feedbackRouteParams(resetPage = false) {
    const params = {
        status: filterForm.status || undefined,
        category: filterForm.category || undefined,
        project_id: filterForm.project_id || undefined,
        mine: filterForm.mine || undefined,
        scope: filterForm.scope || undefined,
        q: filterForm.q || undefined,
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
    () => [filterForm.status, filterForm.category, filterForm.project_id, filterForm.mine, filterForm.scope],
    () => navigate(true),
);

watch(perPage, () => navigate(true));

function clearFilters() {
    filterForm.q = '';
    filterForm.status = '';
    filterForm.category = '';
    filterForm.project_id = '';
    filterForm.mine = '';
    filterForm.scope = '';
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
                @click="openFilterPanel()"
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
          </div>
        </div>

        <Transition name="fade-slide">
          <div
            v-if="hasFilterRow"
            class="grid grid-cols-1 gap-3 border-t border-slate-100 px-0 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
          >
            <DatagridFilterField v-if="visibleFilters.status">
              <select
                v-model="filterForm.status"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Trạng thái"
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
              <select
                v-model="filterForm.category"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Phân loại"
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
              <select
                v-model="filterForm.project_id"
                :class="FILTER_CONTROL_CLASS"
                aria-label="Dự án"
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

            <DatagridFilterField
              v-if="visibleFilters.mine"
              class="flex items-center"
            >
              <label class="flex h-10 w-full cursor-pointer items-center gap-2 rounded-btn border border-slate-200 px-3 text-sm text-slate-600">
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
              class="flex items-center sm:col-span-2 xl:col-span-2"
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
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-4 py-2.5 font-medium">
                Mã
              </th>
              <th class="px-4 py-2.5 font-medium">
                Tiêu đề
              </th>
              <th class="px-4 py-2.5 font-medium">
                Phân loại
              </th>
              <th class="px-4 py-2.5 font-medium">
                Người gửi
              </th>
              <th class="px-4 py-2.5 font-medium">
                Trạng thái
              </th>
              <th class="px-4 py-2.5 font-medium">
                Người xử lý
              </th>
              <th class="px-4 py-2.5 font-medium text-right">
                Thao tác
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="f in feedback.data"
              :key="f.id"
              class="transition-colors hover:bg-slate-50/80"
            >
              <td class="px-4 py-2.5 font-mono text-xs text-slate-500">
                {{ f.code }}
              </td>
              <td class="px-4 py-2.5">
                <Link
                  :href="`/feedback/${f.id}`"
                  class="font-medium text-slate-700 hover:text-brand"
                >
                  {{ f.title }}
                </Link>
                <p
                  v-if="f.project?.name"
                  class="mt-0.5 text-[11px] text-slate-400"
                >
                  {{ f.project.name }}
                </p>
              </td>
              <td class="px-4 py-2.5">
                <Badge
                  :label="f.category.label"
                  :color="f.category.color"
                />
              </td>
              <td class="px-4 py-2.5 text-slate-500">
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
              <td class="px-4 py-2.5">
                <div class="flex items-center justify-end gap-1">
                  <Link
                    :href="`/feedback/${f.id}`"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-brand"
                    title="Chi tiết"
                  >
                    <AppIcon
                      name="eye"
                      :size="16"
                    />
                  </Link>
                  <button
                    v-if="f.can?.delete"
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
                    title="Xoá phản hồi"
                    @click="remove(f)"
                  >
                    <AppIcon
                      name="delete"
                      :size="16"
                    />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!feedback.data.length">
              <td
                colspan="7"
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
            :colspan="7"
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
