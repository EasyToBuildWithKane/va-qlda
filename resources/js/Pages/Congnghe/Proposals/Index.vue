<script setup>
import {
    computed, onBeforeUnmount, onMounted, reactive, ref, watch,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { datetime } from '@/composables/useFormat';

const PER_PAGE_OPTIONS = [10, 15, 20, 30];
const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái', default: false },
    { key: 'email_pending', label: 'Chưa gửi email', default: false },
];

const STATUS_TONE = {
    new: 'violet',
    triaged: 'sky',
    in_progress: 'amber',
    done: 'emerald',
    rejected: 'slate',
};

const props = defineProps({
    proposals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const filterPanelDdRef = ref(null);
const perPage = ref(Number(props.filters.per_page) || props.proposals.meta?.per_page || 20);

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
} = useVisibleFilterControls(FILTER_CONTROLS, 'va-qlda.congnghe-proposals.filters.v1');

const filterForm = reactive({
    status: props.filters.status ?? '',
    email_pending: props.filters.email_pending ? '1' : '',
    q: props.filters.q ?? '',
});

const listBadge = computed(() => props.summary.new ?? props.summary.total ?? null);

function routeParams(resetPage = false) {
    const params = {
        status: filterForm.status || undefined,
        email_pending: filterForm.email_pending || undefined,
        q: filterForm.q || undefined,
        per_page: perPage.value,
    };
    if (resetPage) params.page = 1;
    return params;
}

function navigate(resetPage = false) {
    router.get(route('congnghe.proposals.index'), routeParams(resetPage), {
        preserveState: true,
        replace: true,
    });
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => navigate(true), 350);
});

watch(
    () => [filterForm.status, filterForm.email_pending],
    () => navigate(true),
);

watch(perPage, () => navigate(true));

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) return;
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function statusLabel(value) {
    const opt = props.options.statuses?.find((s) => s.value === value);
    return opt?.label ?? value;
}
</script>

<template>
  <Head title="Đề xuất phần mềm" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Đề xuất phần mềm"
        subtitle="Tiếp nhận yêu cầu phần mềm từ cổng Phòng Công nghệ"
        icon="rocket"
        icon-color="brand"
        :badge="listBadge"
      >
        <Link
          href="/congnghe/de-xuat"
          class="btn-ghost inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-xs font-semibold"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Form công khai
        </Link>
      </PageHeader>
    </template>

    <section
      class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4"
      aria-label="Thống kê đề xuất"
    >
      <div class="rounded-card border border-slate-200/80 bg-white px-4 py-3 shadow-sm">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
          Tổng
        </p>
        <p class="font-display text-2xl tabular-nums text-slate-900">
          {{ summary.total ?? 0 }}
        </p>
      </div>
      <div class="rounded-card border border-violet-200/80 bg-violet-50/50 px-4 py-3 shadow-sm">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-700/80">
          Mới
        </p>
        <p class="font-display text-2xl tabular-nums text-violet-900">
          {{ summary.new ?? 0 }}
        </p>
      </div>
      <div class="rounded-card border border-amber-200/80 bg-amber-50/40 px-4 py-3 shadow-sm">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800/80">
          Đang xử lý
        </p>
        <p class="font-display text-2xl tabular-nums text-amber-950">
          {{ summary.in_progress ?? 0 }}
        </p>
      </div>
      <div class="rounded-card border border-emerald-200/80 bg-emerald-50/40 px-4 py-3 shadow-sm">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800/80">
          Hoàn thành
        </p>
        <p class="font-display text-2xl tabular-nums text-emerald-950">
          {{ summary.done ?? 0 }}
        </p>
      </div>
    </section>

    <div class="card overflow-hidden">
      <div class="border-b border-slate-100 px-5 py-4">
        <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
          <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              hide-label
              stretch
              inline-actions
              input-height="h-10"
              placeholder="Tìm mã, tiêu đề, người gửi, email…"
              aria-label="Tìm đề xuất"
            />
          </div>
          <div
            ref="filterPanelDdRef"
            class="relative shrink-0"
          >
            <DatagridToolbarActionButton
              label="Lọc"
              icon="filter"
              :active="showFilterPanelDd"
              :badge="enabledFilterControlCount > 0 ? enabledFilterControlCount : null"
              @click="openFilterPanel()"
            />
            <FilterVisibilityDropdown
              v-model="visibleFilters"
              :show="showFilterPanelDd"
              :anchor-ref="filterPanelDdRef"
              :controls="FILTER_CONTROLS"
              input-id-prefix="cn-proposal-filter-vis"
              @persist="persistVisibleFilters"
            />
          </div>
        </div>

        <div
          v-if="hasFilterRow"
          class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
        >
          <DatagridFilterField
            v-if="visibleFilters.status"
            label="Trạng thái"
          >
            <select
              v-model="filterForm.status"
              :class="FILTER_CONTROL_CLASS"
            >
              <option value="">
                Trạng thái
              </option>
              <option
                v-for="opt in options.statuses"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </DatagridFilterField>
          <DatagridFilterField
            v-if="visibleFilters.email_pending"
            label="Email"
          >
            <select
              v-model="filterForm.email_pending"
              :class="FILTER_CONTROL_CLASS"
            >
              <option value="">
                Email
              </option>
              <option value="1">
                Chưa gửi email ({{ summary.email_pending ?? 0 }})
              </option>
            </select>
          </DatagridFilterField>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-sm">
          <thead class="border-b border-slate-100 bg-slate-50/80 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">
                Mã
              </th>
              <th class="px-5 py-3">
                Tiêu đề
              </th>
              <th class="px-5 py-3">
                Người gửi
              </th>
              <th class="px-5 py-3">
                Phòng ban
              </th>
              <th class="px-5 py-3">
                Trạng thái
              </th>
              <th class="px-5 py-3">
                Email
              </th>
              <th class="px-5 py-3">
                Ngày gửi
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr
              v-for="row in proposals.data"
              :key="row.id"
              class="hover:bg-slate-50/80"
            >
              <td class="px-5 py-3 font-mono text-xs text-slate-600">
                <Link
                  :href="route('congnghe.proposals.show', row.id)"
                  class="font-semibold text-brand hover:underline"
                >
                  {{ row.reference_code ?? '—' }}
                </Link>
              </td>
              <td class="max-w-[220px] px-5 py-3">
                <Link
                  :href="route('congnghe.proposals.show', row.id)"
                  class="line-clamp-2 font-medium text-slate-900 hover:text-brand"
                >
                  {{ row.title }}
                </Link>
              </td>
              <td class="px-5 py-3">
                <div class="font-medium text-slate-800">
                  {{ row.submitter_name }}
                </div>
                <div class="text-xs text-slate-500">
                  {{ row.submitter_email }}
                </div>
              </td>
              <td class="px-5 py-3 text-slate-600">
                {{ row.department }}
              </td>
              <td class="px-5 py-3">
                <Badge
                  :tone="STATUS_TONE[row.status?.value] ?? 'slate'"
                  size="sm"
                >
                  {{ row.status?.label ?? statusLabel(row.status?.value) }}
                </Badge>
              </td>
              <td class="px-5 py-3">
                <Badge
                  v-if="row.email_sent_at"
                  tone="emerald"
                  size="sm"
                >
                  Đã gửi
                </Badge>
                <Badge
                  v-else
                  tone="amber"
                  size="sm"
                >
                  Chưa gửi
                </Badge>
              </td>
              <td class="px-5 py-3 text-slate-600 tabular-nums">
                {{ datetime(row.created_at) }}
              </td>
            </tr>
            <tr v-if="!proposals.data?.length">
              <td
                colspan="7"
                class="px-5 py-12 text-center text-slate-500"
              >
                Chưa có đề xuất nào.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <DatagridPaginationFooter
        variant="bar"
        :meta="proposals.meta"
        :per-page="perPage"
        :per-page-options="PER_PAGE_OPTIONS"
        @update:per-page="(v) => { perPage = v; }"
      />
    </div>
  </AppLayout>
</template>
