<script setup>
/* eslint-disable vue/no-v-html -- Laravel pagination link labels contain HTML entities */
import { reactive, ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import BugFormModal from '@/modules/project/components/BugFormModal.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { isAnchoredDropdownTarget } from '@/shared/composables/useAnchoredDropdownStyle';

const props = defineProps({
    bugs: { type: Object, required: true }, // { data, meta, links }
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const modal = ref(false);
const filterPanelDdRef = ref(null);

const BUG_FILTER_CONTROLS = [
    { key: 'status', label: 'Trạng thái' },
    { key: 'severity', label: 'Mức độ' },
    { key: 'project', label: 'Dự án' },
    { key: 'mine', label: 'Tôi sửa' },
];

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(BUG_FILTER_CONTROLS, 'va-qlda.bugs.visible-filters');

function onToolbarClickOutside(e) {
    if (isAnchoredDropdownTarget(e.target)) {
        return;
    }
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

const filterForm = reactive({
    status: props.filters.status ?? '',
    severity: props.filters.severity ?? '',
    project_id: props.filters.project_id ?? '',
    mine: props.filters.mine ? '1' : '',
    q: props.filters.q ?? '',
});

const appliedFilterCount = computed(() =>
    [filterForm.status, filterForm.severity, filterForm.project_id, filterForm.mine]
        .filter((v) => v !== '' && v != null).length,
);

function bugRouteParams() {
    return {
        status: filterForm.status || undefined,
        severity: filterForm.severity || undefined,
        project_id: filterForm.project_id || undefined,
        mine: filterForm.mine || undefined,
        q: filterForm.q || undefined,
    };
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => {
        router.get('/bugs', bugRouteParams(), { preserveState: true, replace: true });
    }, 350);
});

watch(
    () => [filterForm.status, filterForm.severity, filterForm.project_id, filterForm.mine],
    () => router.get('/bugs', bugRouteParams(), { preserveState: true, replace: true }),
);

function clearFilters() {
    filterForm.q = '';
    filterForm.status = '';
    filterForm.severity = '';
    filterForm.project_id = '';
    filterForm.mine = '';
}
</script>

<template>
  <Head title="Quản lý lỗi" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý lỗi (Bug)"
        subtitle="Theo dõi và xử lý các vấn đề kỹ thuật"
        icon="bug"
        icon-color="rose"
        :badge="summary.open ?? null"
      />
    </template>

    <div class="mb-5 grid grid-cols-3 gap-4">
      <div class="card p-4">
        <p class="text-sm text-slate-500">
          Đang mở
        </p><p class="mt-1 font-display text-2xl font-bold text-rose-500">
          {{ summary.open ?? 0 }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-sm text-slate-500">
          Nghiêm trọng
        </p><p class="mt-1 font-display text-2xl font-bold text-amber-600">
          {{ summary.critical ?? 0 }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-sm text-slate-500">
          Đã sửa
        </p><p class="mt-1 font-display text-2xl font-bold text-emerald-600">
          {{ summary.resolved ?? 0 }}
        </p>
      </div>
    </div>

    <div class="card mb-4 overflow-visible">
      <div class="border-b border-slate-100 px-5 py-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <DatagridToolbarSearch
              v-model="filterForm.q"
              input-id="bugs-search"
              placeholder="Mã bug, tiêu đề…"
            />
            <div
              ref="filterPanelDdRef"
              class="relative shrink-0"
            >
              <button
                type="button"
                class="inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border px-2.5 text-xs font-medium transition"
                :class="showFilterPanelDd ? 'border-brand/40 bg-brand/5 text-brand' : 'border-slate-200 bg-white text-slate-600'"
                :title="`Hiển thị bộ lọc (${enabledFilterControlCount}/${FILTER_CONTROLS.length})`"
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
                :anchor="filterPanelDdRef"
                :show="showFilterPanelDd"
                :controls="FILTER_CONTROLS"
                @persist="persistVisibleFilters"
              />
            </div>
          </div>
          <button
            v-if="can.create"
            type="button"
            class="btn-primary h-9 shrink-0 gap-1.5 px-4 text-sm"
            @click="modal = true"
          >
            <AppIcon
              name="add"
              :size="15"
            />
            Báo lỗi
          </button>
        </div>
      </div>
      <div
        v-if="hasFilterRow"
        class="flex flex-wrap items-center gap-2 border-t border-slate-100 px-5 py-3"
      >
        <select
          v-if="visibleFilters.status"
          v-model="filterForm.status"
          class="input h-9 w-36 text-sm"
        >
          <option value="">
            Trạng thái: Tất cả
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
          class="input h-9 w-36 text-sm"
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
          class="input h-9 min-w-[11rem] text-sm sm:w-48"
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
        <label
          v-if="visibleFilters.mine"
          class="inline-flex h-9 items-center gap-2 rounded-btn border border-slate-200 bg-white px-3 text-sm text-slate-600"
        >
          <input
            v-model="filterForm.mine"
            true-value="1"
            false-value=""
            type="checkbox"
            class="rounded"
          >
          Tôi sửa
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
    </div>

    <div class="card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-2 font-medium">
              Mã
            </th>
            <th class="px-4 py-2 font-medium">
              Tiêu đề
            </th>
            <th class="px-4 py-2 font-medium">
              Dự án
            </th>
            <th class="px-4 py-2 font-medium">
              Mức độ
            </th>
            <th class="px-4 py-2 font-medium">
              Trạng thái
            </th>
            <th class="px-4 py-2 font-medium">
              Người sửa
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="b in bugs.data"
            :key="b.id"
            class="hover:bg-slate-50"
          >
            <td class="px-4 py-2.5 font-mono text-xs text-slate-500">
              {{ b.code }}
            </td>
            <td class="px-4 py-2.5">
              <Link
                :href="`/bugs/${b.id}`"
                class="font-medium text-slate-700 hover:text-brand"
              >
                {{ b.title }}
              </Link>
            </td>
            <td class="px-4 py-2.5 text-slate-500">
              {{ b.project?.name }}
            </td>
            <td class="px-4 py-2.5">
              <Badge
                :label="b.severity.label"
                :color="b.severity.color"
              />
            </td>
            <td class="px-4 py-2.5">
              <Badge
                :label="b.status.label"
                :color="b.status.color"
              />
            </td>
            <td class="px-4 py-2.5">
              <div
                v-if="b.assignee"
                class="flex items-center gap-1.5"
              >
                <Avatar
                  :name="b.assignee.name"
                  :src="b.assignee.avatar_path"
                  :size="22"
                />
                <span class="text-slate-600">{{ b.assignee.name }}</span>
              </div>
              <span
                v-else
                class="text-slate-400"
              >—</span>
            </td>
          </tr>
          <tr v-if="!bugs.data.length">
            <td
              colspan="6"
              class="px-4 py-12 text-center text-slate-400"
            >
              Không có bug nào.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="bugs.meta && bugs.meta.last_page > 1"
      class="mt-4 flex flex-wrap gap-1"
    >
      <template
        v-for="(link, i) in bugs.meta.links"
        :key="i"
      >
        <Link
          v-if="link.url"
          :href="link.url"
          class="rounded-btn px-3 py-1.5 text-sm"
          :class="link.active ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100'"
        >
          <span v-html="link.label" />
        </Link>
      </template>
    </div>

    <BugFormModal
      :show="modal"
      :projects="options.projects"
      :employees="options.employees"
      :severity-options="options.severity"
      :status-options="options.status"
      :priority-options="options.priority"
      @close="modal = false"
    />
  </AppLayout>
</template>
