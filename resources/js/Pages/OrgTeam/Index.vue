<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import OrgTeamOverviewSummaryBar from '@/modules/people/components/OrgTeamOverviewSummaryBar.vue';
import OrgGraph from '@/modules/people/components/OrgGraph.vue';
import OrgTeamFormModal from '@/modules/people/components/OrgTeamFormModal.vue';
import OrgTeamPersonDetailDrawer from '@/modules/people/components/OrgTeamPersonDetailDrawer.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const ORG_GRAPH_FILTER_CONTROLS = [
    { key: 'root_id', label: 'Nhóm', default: false },
    { key: 'role', label: 'Vai trò', default: false },
    { key: 'status', label: 'Trạng thái', default: false },
];

const ROLE_OPTIONS = [
    { value: 'all', label: 'Vai trò' },
    { value: 'leaders', label: 'Quản lý' },
    { value: 'members', label: 'Thành viên' },
];

const STATUS_OPTIONS = [
    { value: 'all', label: 'Trạng thái' },
    { value: 'active', label: 'Đang hoạt động' },
    { value: 'inactive', label: 'Ngừng hoạt động' },
];

const props = defineProps({
    trees: { type: Array, default: () => [] },
    overview: { type: Object, default: () => ({}) },
    parentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    branchOptions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const filter = ref({ query: '', rootId: null, role: 'all', status: 'all' });

const selectedPerson = ref(null);
const personDrawerOpen = ref(false);

const modalOpen = ref(false);
const forceRoot = ref(false);

const filterPanelDdRef = ref(null);

const primaryEditHref = computed(() => {
    if (!props.trees.length) {
        return null;
    }
    const sorted = [...props.trees].sort((a, b) => b.id - a.id);

    return `/org-teams/${sorted[0].id}/edit`;
});

const {
    visibleFilters,
    showFilterPanelDd,
    enabledFilterControlCount,
    hasFilterRow,
    persistVisibleFilters,
    openFilterPanel,
    FILTER_CONTROLS,
} = useVisibleFilterControls(
    ORG_GRAPH_FILTER_CONTROLS,
    'va-qlda.org-teams-graph.visible-filters.v1',
);

const rootOptions = computed(() => props.trees.map((t) => ({ id: t.id, name: t.name })));

function onToolbarClickOutside(e) {
    if (e.target.closest?.('[data-filter-visibility-panel]')) {
        return;
    }
    if (filterPanelDdRef.value && !filterPanelDdRef.value.contains(e.target)) {
        showFilterPanelDd.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onToolbarClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onToolbarClickOutside));

function onOverviewQuickFilter({ status, role, reset }) {
    if (reset) {
        filter.value = {
            query: filter.value.query,
            rootId: filter.value.rootId,
            role: 'all',
            status: 'all',
        };
        return;
    }
    filter.value = {
        ...filter.value,
        role: role ?? filter.value.role,
        status: status ?? filter.value.status,
    };
}

function patchFilter(partial) {
    filter.value = { ...filter.value, ...partial };
}

/* ---------------- person / leader drawer ---------------- */
function openPerson(person) {
    selectedPerson.value = {
        name: person.name,
        avatar: person.avatar ?? null,
        isLeader: person.isLeader ?? false,
        teamName: person.teamName ?? null,
        sectionTitle: person.sectionTitle ?? null,
        branchLabel: person.branchLabel ?? null,
        roleTitle: person.roleTitle ?? null,
        email: person.email ?? null,
        code: person.code ?? null,
    };
    personDrawerOpen.value = true;
}

function openLeader(node) {
    const leader = node.team?.leader;
    if (!leader) return;
    selectedPerson.value = {
        name: leader.name,
        avatar: leader.avatar_path ?? null,
        isLeader: true,
        teamName: node.team.name,
        sectionTitle: null,
        branchLabel: 'Quản lý',
        roleTitle: leader.role_title ?? null,
        email: leader.email ?? null,
        code: leader.code ?? null,
    };
    personDrawerOpen.value = true;
}

function openCreateRoot() {
    forceRoot.value = true;
    modalOpen.value = true;
}
</script>

<template>
  <Head title="Sơ đồ tổ chức" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Sơ đồ tổ chức"
        icon="org-teams"
        icon-color="brand"
        :badge="overview.people_total ?? null"
      >
        <div class="flex shrink-0 flex-wrap items-center gap-2">
          <Link
            v-if="can.create && primaryEditHref"
            :href="primaryEditHref"
            class="btn-secondary flex h-9 items-center gap-1.5 px-3 text-xs"
          >
            <AppIcon
              name="edit"
              :size="15"
            />
            Chỉnh sửa cấu trúc
          </Link>
          <Link
            href="/org-teams/members"
            class="btn-secondary flex h-9 items-center gap-1.5 px-3 text-xs"
          >
            <AppIcon
              name="members"
              :size="15"
            />
            Thành viên sơ đồ
          </Link>
          <button
            v-if="can.create"
            type="button"
            class="btn-primary flex h-9 items-center gap-1.5 px-3 text-xs"
            @click="openCreateRoot()"
          >
            <AppIcon
              name="plus"
              :size="15"
            />
            Thêm Nhóm
          </button>
        </div>
      </PageHeader>
    </template>

    <!-- Empty -->
    <div
      v-if="!trees.length"
      class="card flex flex-col items-center justify-center gap-4 py-20 text-center"
    >
      <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-brand/10 text-brand">
        <AppIcon
          name="org-teams"
          :size="32"
        />
      </div>
      <div>
        <p class="font-display text-base font-semibold text-slate-800">
          Chưa có Nhóm nào
        </p>
        <p class="mt-1 max-w-sm text-sm text-slate-500">
          Tạo team gốc đầu tiên để dựng sơ đồ tổ chức tương tác.
        </p>
      </div>
      <button
        v-if="can.create"
        type="button"
        class="btn-primary text-sm"
        @click="openCreateRoot()"
      >
        Thêm Nhóm đầu tiên
      </button>
    </div>

    <div
      v-else
      class="space-y-4"
    >
      <OrgTeamOverviewSummaryBar
        :summary="overview"
        :active-status="filter.status"
        :active-role="filter.role"
        @quick-filter="onOverviewQuickFilter"
      />

      <div class="card overflow-visible">
        <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
          <div class="flex w-full min-w-0 flex-wrap items-center gap-2 lg:flex-nowrap">
            <div class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto">
              <DatagridToolbarSearch
                v-model="filter.query"
                input-id="org-graph-search"
                placeholder="Tìm nhân sự, chức danh, mã NV…"
                stretch
                inline-actions
                hide-label
                input-height="h-10"
                aria-label="Tìm trên sơ đồ tổ chức"
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
              class="mt-4 grid grid-cols-1 gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6"
            >
              <DatagridFilterField v-if="visibleFilters.root_id">
                <select
                  :value="filter.rootId ?? ''"
                  :class="FILTER_CONTROL_CLASS"
                  aria-label="Nhóm"
                  @change="patchFilter({ rootId: $event.target.value ? Number($event.target.value) : null })"
                >
                  <option value="">
                    Nhóm
                  </option>
                  <option
                    v-for="root in rootOptions"
                    :key="root.id"
                    :value="root.id"
                  >
                    {{ root.name }}
                  </option>
                </select>
              </DatagridFilterField>

              <DatagridFilterField v-if="visibleFilters.role">
                <select
                  :value="filter.role"
                  :class="FILTER_CONTROL_CLASS"
                  aria-label="Vai trò"
                  @change="patchFilter({ role: $event.target.value })"
                >
                  <option
                    v-for="opt in ROLE_OPTIONS"
                    :key="opt.value"
                    :value="opt.value"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </DatagridFilterField>

              <DatagridFilterField v-if="visibleFilters.status">
                <select
                  :value="filter.status"
                  :class="FILTER_CONTROL_CLASS"
                  aria-label="Trạng thái"
                  @change="patchFilter({ status: $event.target.value })"
                >
                  <option
                    v-for="opt in STATUS_OPTIONS"
                    :key="opt.value"
                    :value="opt.value"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </DatagridFilterField>
            </div>
          </Transition>
        </div>

        <OrgGraph
          :trees="trees"
          :filter="filter"
          @select-person="openPerson"
          @select-leader="openLeader"
        />
      </div>
    </div>

    <OrgTeamFormModal
      :show="modalOpen"
      :team="null"
      :parent-options="parentOptions"
      :employees="employees"
      :branch-options="branchOptions"
      :preset-parent-id="null"
      :force-root="forceRoot"
      @close="modalOpen = false; forceRoot = false"
      @saved="modalOpen = false; forceRoot = false"
    />

    <OrgTeamPersonDetailDrawer
      :show="personDrawerOpen"
      :person="selectedPerson"
      @close="personDrawerOpen = false"
    />
  </AppLayout>
</template>
