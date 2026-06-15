<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import OrgTeamMembersSummaryBar from '@/modules/people/components/OrgTeamMembersSummaryBar.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const MEMBERS_FILTER_CONTROLS = [
    { key: 'root_id', label: 'Ban / Khối', default: false },
    { key: 'team_id', label: 'Nhóm', default: false },
    { key: 'branch', label: 'Nhánh / vai trò', default: false },
];

const props = defineProps({
    roster: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    filteredCount: { type: Number, default: 0 },
    rootOptions: { type: Array, default: () => [] },
    teamOptions: { type: Array, default: () => [] },
    branchOptions: { type: Array, default: () => [] },
});

const filterForm = reactive({
    q: props.filters.q ?? '',
    root_id: props.filters.root_id ?? '',
    team_id: props.filters.team_id ?? '',
    branch: props.filters.branch ?? '',
    status: props.filters.status ?? 'all',
});

const perPage = ref(Number(props.filters.per_page) || props.roster.meta?.per_page || 24);

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(
    MEMBERS_FILTER_CONTROLS,
    'va-qlda.org-team-members.visible-filters.v1',
);

const filterPanelDdRef = ref(null);

const rows = computed(() => props.roster.data ?? []);

const statusTabs = computed(() => [
    { value: 'all', label: 'Tất cả' },
    { value: 'active', label: 'Đang hoạt động' },
    { value: 'inactive', label: 'Ngừng' },
]);

function rowAssignments(row) {
    const raw = row?.assignments;
    if (Array.isArray(raw)) {
        return raw;
    }
    if (raw && typeof raw === 'object') {
        return Object.values(raw);
    }
    return [];
}

function assignmentBranchColor(branchValue) {
    const match = props.branchOptions.find((b) => b.value === branchValue);
    return match?.color ?? 'violet';
}

function showActiveFiltersInPanel() {
    const active = {
        root_id: filterForm.root_id,
        team_id: filterForm.team_id,
        branch: filterForm.branch,
    };
    let changed = false;
    for (const [key, val] of Object.entries(active)) {
        if (val != null && val !== '' && !visibleFilters.value[key]) {
            visibleFilters.value[key] = true;
            changed = true;
        }
    }
    if (changed) {
        persistVisibleFilters();
    }
}

let debounce = null;
watch(
    () => filterForm.q,
    () => {
        clearTimeout(debounce);
        debounce = setTimeout(() => load(true), 350);
    },
);

function routeParams(resetPage = false) {
    const params = {
        q: filterForm.q || undefined,
        root_id: filterForm.root_id || undefined,
        team_id: filterForm.team_id || undefined,
        branch: filterForm.branch || undefined,
        status: filterForm.status === 'all' ? undefined : filterForm.status,
        per_page: perPage.value,
    };
    if (resetPage) {
        params.page = 1;
    }

    return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null));
}

function load(resetPage = true) {
    router.get('/org-teams/members', routeParams(resetPage), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function setStatus(val) {
    filterForm.status = val;
    load(true);
}

function onSummaryStatus(status) {
    setStatus(status);
}

function onPerPageChange(val) {
    perPage.value = val;
    load(true);
}

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) {
        return;
    }
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}

onMounted(() => {
    showActiveFiltersInPanel();
    document.addEventListener('mousedown', onToolbarClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onToolbarClickOutside);
});
</script>

<template>
  <Head title="Thành viên phòng" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Thành viên phòng"
        icon="members"
        icon-color="brand"
        :badge="summary.total"
      >
        <Link
          href="/org-teams"
          class="btn-secondary flex h-9 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="org-teams"
            :size="15"
          />
          Sơ đồ team
        </Link>
      </PageHeader>
    </template>

    <div class="space-y-4">
      <OrgTeamMembersSummaryBar
        :summary="summary"
        :active-status="filterForm.status"
        @filter-status="onSummaryStatus"
      />

      <div class="card overflow-hidden">
        <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
          <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
            <div class="min-w-0 w-full basis-full lg:flex-1 lg:basis-auto">
              <DatagridToolbarSearch
                v-model="filterForm.q"
                hide-label
                stretch
                inline-actions
                input-height="h-10"
                placeholder="Tìm tên, mã, email, chức danh…"
                aria-label="Tìm thành viên"
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

              <div class="flex shrink-0 items-center gap-1 rounded-lg bg-slate-100 p-1">
                <button
                  v-for="t in statusTabs"
                  :key="t.value"
                  type="button"
                  class="rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors"
                  :class="filterForm.status === t.value
                    ? 'bg-white text-slate-800 shadow-sm'
                    : 'text-slate-500 hover:text-slate-700'"
                  @click="setStatus(t.value)"
                >
                  {{ t.label }}
                </button>
              </div>
            </div>
          </div>

          <Transition name="fade-slide">
            <div
              v-if="hasFilterRow"
              class="mt-4 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
            >
              <DatagridFilterField v-if="visibleFilters.root_id">
                <select
                  v-model="filterForm.root_id"
                  :class="FILTER_CONTROL_CLASS"
                  aria-label="Ban / Khối"
                  @change="load(true)"
                >
                  <option value="">
                    Ban / Khối
                  </option>
                  <option
                    v-for="r in rootOptions"
                    :key="r.id"
                    :value="r.id"
                  >
                    {{ r.name }}
                  </option>
                </select>
              </DatagridFilterField>

              <DatagridFilterField v-if="visibleFilters.team_id">
                <select
                  v-model="filterForm.team_id"
                  :class="FILTER_CONTROL_CLASS"
                  aria-label="Nhóm"
                  @change="load(true)"
                >
                  <option value="">
                    Nhóm
                  </option>
                  <option
                    v-for="t in teamOptions"
                    :key="t.id"
                    :value="t.id"
                  >
                    {{ t.label }}
                  </option>
                </select>
              </DatagridFilterField>

              <DatagridFilterField v-if="visibleFilters.branch">
                <select
                  v-model="filterForm.branch"
                  :class="FILTER_CONTROL_CLASS"
                  aria-label="Nhánh / vai trò"
                  @change="load(true)"
                >
                  <option value="">
                    Nhánh / vai trò
                  </option>
                  <option
                    v-for="b in branchOptions"
                    :key="b.value"
                    :value="b.value"
                  >
                    {{ b.label }}
                  </option>
                </select>
              </DatagridFilterField>
            </div>
          </Transition>
        </div>

        <EmptyState
          v-if="!rows.length"
          icon="members"
          title="Không có thành viên phù hợp"
          description="Thử đổi từ khoá hoặc bật bộ lọc Ban/Khối, nhóm, nhánh trong nút Lọc."
        />

        <div
          v-else
          class="overflow-x-auto"
        >
          <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50/80 text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3 sm:px-5">
                  Thành viên
                </th>
                <th class="px-4 py-3">
                  Chức danh
                </th>
                <th class="px-4 py-3">
                  Vị trí trong sơ đồ
                </th>
                <th class="px-4 py-3 sm:pr-5">
                  Nhánh / nhóm
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="row in rows"
                :key="row.id"
                class="transition-colors hover:bg-slate-50/60"
              >
                <td class="px-4 py-3.5 sm:px-5">
                  <Link
                    :href="`/members/${row.id}`"
                    class="group flex items-center gap-3"
                  >
                    <Avatar
                      :name="row.name"
                      :src="row.avatar_path"
                      :size="40"
                    />
                    <span class="min-w-0">
                      <span class="flex items-center gap-2">
                        <span class="truncate font-semibold text-slate-800 group-hover:text-brand">
                          {{ row.name }}
                        </span>
                        <Badge
                          v-if="row.is_leader"
                          label="Trưởng nhóm"
                          color="amber"
                        />
                        <span
                          v-if="!row.is_active"
                          class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500"
                        >
                          Ngừng
                        </span>
                      </span>
                      <span class="block truncate text-xs text-slate-400">
                        {{ row.code }}
                      </span>
                    </span>
                  </Link>
                </td>
                <td class="max-w-[12rem] px-4 py-3.5 text-slate-600">
                  <span class="line-clamp-2">
                    {{ row.role_title || '—' }}
                  </span>
                </td>
                <td class="px-4 py-3.5">
                  <ul
                    v-if="rowAssignments(row).length"
                    class="space-y-1.5"
                  >
                    <li
                      v-for="(a, idx) in rowAssignments(row)"
                      :key="`${row.id}-path-${idx}`"
                      class="text-xs leading-snug text-slate-700"
                    >
                      <span class="font-medium">{{ a.path || row.primary_org || '—' }}</span>
                      <span
                        v-if="a.is_leader"
                        class="ml-1 text-[10px] font-semibold uppercase text-brand"
                      >
                        · Trưởng nhóm
                      </span>
                    </li>
                  </ul>
                  <span
                    v-else-if="row.primary_org"
                    class="text-xs font-medium text-slate-700"
                  >{{ row.primary_org }}</span>
                  <span
                    v-else
                    class="text-xs text-slate-400"
                  >—</span>
                </td>
                <td class="px-4 py-3.5 sm:pr-5">
                  <div
                    v-if="rowAssignments(row).length"
                    class="flex flex-col gap-1.5"
                  >
                    <template
                      v-for="(a, idx) in rowAssignments(row)"
                      :key="`meta-${row.id}-${idx}`"
                    >
                      <div class="flex flex-wrap items-center gap-1">
                        <span
                          v-if="a.team_name"
                          class="rounded-md bg-brand/5 px-1.5 py-0.5 text-[11px] font-medium text-brand"
                        >
                          {{ a.team_name }}
                        </span>
                        <span
                          v-if="a.section"
                          class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-600"
                        >
                          {{ a.section }}
                        </span>
                        <Badge
                          v-if="a.branch_label"
                          :label="a.branch_label"
                          :color="assignmentBranchColor(a.branch)"
                        />
                      </div>
                    </template>
                  </div>
                  <span
                    v-else
                    class="text-xs text-slate-400"
                  >
                    —
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <DatagridPaginationFooter
          v-if="roster.meta"
          :meta="roster.meta"
          :per-page="perPage"
          :per-page-options="[24, 48, 96]"
          @update:per-page="onPerPageChange"
        />
      </div>
    </div>
  </AppLayout>
</template>
