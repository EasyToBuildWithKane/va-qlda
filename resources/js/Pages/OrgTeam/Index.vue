<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import DatagridFilterField from '@/shared/ui/DatagridFilterField.vue';
import FilterVisibilityDropdown from '@/shared/ui/FilterVisibilityDropdown.vue';
import OrgTeamOverviewSummaryBar from '@/modules/people/components/OrgTeamOverviewSummaryBar.vue';
import OrgGraph from '@/modules/people/components/OrgGraph.vue';
import OrgTeamChart from '@/modules/people/components/OrgTeamChart.vue';
import OrgTeamChartCanvas from '@/modules/people/components/OrgTeamChartCanvas.vue';
import OrgTeamFormModal from '@/modules/people/components/OrgTeamFormModal.vue';
import OrgTeamPersonDetailDrawer from '@/modules/people/components/OrgTeamPersonDetailDrawer.vue';
import { useVisibleFilterControls } from '@/shared/composables/useVisibleFilterControls';
import { useDialog } from '@/composables/useDialog';

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const ORG_GRAPH_FILTER_CONTROLS = [
    { key: 'root_id', label: 'Nhóm', default: false },
    { key: 'role', label: 'Vai trò', default: false },
    { key: 'status', label: 'Trạng thái', default: false },
];

const ROLE_OPTIONS = [
    { value: 'all', label: 'Vai trò' },
    { value: 'leaders', label: 'Trưởng nhóm' },
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
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();

const pageMode = ref('graph');
const filter = ref({ query: '', rootId: null, role: 'all', status: 'all' });

const selectedPerson = ref(null);
const personDrawerOpen = ref(false);

const modalOpen = ref(false);
const editing = ref(null);
const presetParentId = ref(null);
const forceRoot = ref(false);
const pendingSelectNewRoot = ref(false);
const activeRootId = ref(null);

const filterPanelDdRef = ref(null);

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

const modeItems = computed(() => {
    const items = [{ key: 'graph', label: 'Sơ đồ', icon: 'org-teams' }];
    if (props.can.create) {
        items.push({ key: 'edit', label: 'Chỉnh sửa', icon: 'edit' });
    }

    return items;
});

const activeRoot = computed(() => {
    if (!props.trees.length) return null;
    return props.trees.find((t) => t.id === activeRootId.value) ?? props.trees[0];
});

watch(
    () => props.trees,
    (trees) => {
        if (!trees.length) {
            activeRootId.value = null;
            pageMode.value = 'graph';

            return;
        }
        if (pendingSelectNewRoot.value) {
            const newest = [...trees].sort((a, b) => b.id - a.id)[0];
            activeRootId.value = newest?.id ?? trees[0].id;
            pendingSelectNewRoot.value = false;
            pageMode.value = 'edit';

            return;
        }
        if (activeRootId.value == null || !trees.some((t) => t.id === activeRootId.value)) {
            activeRootId.value = trees[0].id;
        }
    },
    { immediate: true, deep: true },
);

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
        branchLabel: 'Trưởng nhóm',
        roleTitle: leader.role_title ?? null,
        email: leader.email ?? null,
        code: leader.code ?? null,
    };
    personDrawerOpen.value = true;
}

/* ---------------- edit mode ---------------- */
function openCreateRoot() {
    editing.value = null;
    presetParentId.value = null;
    forceRoot.value = true;
    pendingSelectNewRoot.value = true;
    modalOpen.value = true;
}

function openCreate(parentId = null) {
    editing.value = null;
    presetParentId.value = parentId;
    forceRoot.value = false;
    modalOpen.value = true;
}

function openEdit(node) {
    editing.value = node;
    presetParentId.value = null;
    modalOpen.value = true;
}

function onAddChild(node) {
    openCreate(node.id);
}

async function onDelete(node) {
    const ok = await dialog.confirm({
        title: 'Xoá nhóm',
        message: `Xoá «${node.name}» và các nhóm bên trong? Không thể hoàn tác.`,
        confirmLabel: 'Xóa',
        variant: 'danger',
    });
    if (!ok) return;
    router.delete(`/org-teams/${node.id}`, { preserveScroll: true });
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
            href="/org-teams/members"
            class="btn-secondary flex h-9 items-center gap-1.5 px-3 text-xs"
          >
            <AppIcon
              name="members"
              :size="15"
            />
            Thành viên phòng
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
            <div
              v-if="pageMode === 'graph'"
              class="min-w-0 w-full basis-full lg:min-w-[10rem] lg:flex-1 lg:basis-auto"
            >
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

            <div
              v-if="pageMode === 'graph'"
              class="flex shrink-0 items-center gap-2"
            >
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

            <div class="ml-auto flex shrink-0 flex-wrap items-center justify-end gap-2">
              <DatagridSegmentedControl
                v-model="pageMode"
                :items="modeItems"
                aria-label="Chế độ trang sơ đồ tổ chức"
                icon-only-below-sm
              />
            </div>
          </div>

          <p
            v-if="pageMode === 'edit' && can.create"
            class="mt-3 text-[11px] text-slate-500"
          >
            Thêm nhóm con trên thẻ nhóm, hoặc «Thêm Nhóm» để tạo nhóm gốc mới.
          </p>

          <Transition name="fade-slide">
            <div
              v-if="pageMode === 'graph' && hasFilterRow"
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

        <template v-if="pageMode === 'graph'">
          <OrgGraph
            :trees="trees"
            :filter="filter"
            @select-person="openPerson"
            @select-leader="openLeader"
          />
        </template>
      </div>

      <!-- Edit mode (preserved tree editor) -->
      <div
        v-if="pageMode === 'edit' && activeRoot"
        class="space-y-4"
      >
        <div
          v-if="trees.length > 1"
          class="flex flex-wrap gap-2"
          role="tablist"
          aria-label="Chọn Nhóm"
        >
          <button
            v-for="root in trees"
            :key="root.id"
            type="button"
            role="tab"
            class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
            :class="activeRoot?.id === root.id
              ? 'border-slate-300 bg-slate-800 text-white'
              : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
            :aria-selected="activeRoot?.id === root.id"
            @click="activeRootId = root.id"
          >
            {{ root.name }}
          </button>
        </div>

        <OrgTeamChartCanvas
          :key="`edit-${activeRoot.id}`"
          :title="activeRoot.name"
          :level-label="`Chỉnh sửa · ${activeRoot.level === 1 ? 'Nhóm' : 'Nhóm con'}`"
        >
          <ul class="org-tree org-tree--root flex min-w-min justify-center">
            <OrgTeamChart
              :node="activeRoot"
              :edit-mode="true"
              :can-manage="!!can.create"
              @edit="openEdit"
              @add-child="onAddChild"
              @delete="onDelete"
              @select-person="openPerson"
            />
          </ul>
        </OrgTeamChartCanvas>
      </div>
    </div>

    <OrgTeamFormModal
      :show="modalOpen"
      :team="editing"
      :parent-options="parentOptions"
      :employees="employees"
      :preset-parent-id="presetParentId"
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
