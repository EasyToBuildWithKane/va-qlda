<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import OrgTeamChart from '@/modules/people/components/OrgTeamChart.vue';
import OrgTeamFormModal from '@/modules/people/components/OrgTeamFormModal.vue';
import OrgTeamPersonDetailDrawer from '@/modules/people/components/OrgTeamPersonDetailDrawer.vue';
import OrgTeamRootCard from '@/modules/people/components/OrgTeamRootCard.vue';
import OrgTeamForestSummaryBar from '@/modules/people/components/OrgTeamForestSummaryBar.vue';
import OrgTeamChartCanvas from '@/modules/people/components/OrgTeamChartCanvas.vue';
import { summarizeForest } from '@/modules/people/composables/useOrgTeamTreeStats.js';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    trees: { type: Array, default: () => [] },
    parentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const modalOpen = ref(false);
const editing = ref(null);
const presetParentId = ref(null);
const forceRoot = ref(false);
const pendingSelectNewRoot = ref(false);
const pageMode = ref('overview');
const activeRootId = ref(null);
const selectedPerson = ref(null);
const personDrawerOpen = ref(false);
const overviewQuery = ref('');

const forestStats = computed(() => summarizeForest(props.trees));

const modeItems = computed(() => {
    const items = [
        { key: 'overview', label: 'Tổng quan', icon: 'grid' },
        { key: 'chart', label: 'Sơ đồ', icon: 'org-teams' },
    ];
    if (props.can.create) {
        items.push({ key: 'edit', label: 'Chỉnh sửa', icon: 'edit' });
    }

    return items;
});

const activeRoot = computed(() => {
    if (!props.trees.length) {
        return null;
    }
    const id = activeRootId.value;
    const match = props.trees.find((t) => t.id === id);

    return match ?? props.trees[0];
});

const filteredOverviewRoots = computed(() => {
    const q = overviewQuery.value.trim().toLowerCase();
    if (!q) {
        return props.trees;
    }

    return props.trees.filter((t) => {
        const name = (t.name ?? '').toLowerCase();
        const leader = (t.leader?.name ?? '').toLowerCase();

        return name.includes(q) || leader.includes(q);
    });
});

watch(
    () => props.trees,
    (trees) => {
        if (!trees.length) {
            activeRootId.value = null;
            pageMode.value = 'overview';

            return;
        }
        if (pendingSelectNewRoot.value) {
            const newest = [...trees].sort((a, b) => b.id - a.id)[0];
            activeRootId.value = newest?.id ?? trees[0].id;
            pendingSelectNewRoot.value = false;
            pageMode.value = 'chart';

            return;
        }
        if (activeRootId.value == null || !trees.some((t) => t.id === activeRootId.value)) {
            activeRootId.value = trees[0].id;
        }
    },
    { immediate: true, deep: true },
);

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
        confirmLabel: 'Xoá',
        variant: 'danger',
    });
    if (!ok) return;
    router.delete(`/org-teams/${node.id}`, { preserveScroll: true });
}

function onSelectPerson(person) {
    selectedPerson.value = person;
    personDrawerOpen.value = true;
}

function closePersonDrawer() {
    personDrawerOpen.value = false;
}

function openChartForRoot(nodeOrId) {
    const id = typeof nodeOrId === 'object' ? nodeOrId.id : nodeOrId;
    activeRootId.value = id;
    pageMode.value = 'chart';
}
</script>

<template>
  <Head title="Quản lý team" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý team"
        icon="org-teams"
        icon-color="brand"
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
            Thêm Ban/Khối
          </button>
        </div>
      </PageHeader>
    </template>

    <div
      v-if="trees.length"
      class="mb-4 flex flex-wrap items-center gap-3"
    >
      <DatagridSegmentedControl
        v-model="pageMode"
        :items="modeItems"
        aria-label="Chế độ trang quản lý team"
      />
      <p
        v-if="pageMode === 'chart'"
        class="text-[11px] text-slate-500"
      >
        Bấm thẻ nhân sự trên sơ đồ để xem chi tiết.
      </p>
      <p
        v-else-if="pageMode === 'edit' && can.create"
        class="text-[11px] text-slate-500"
      >
        Thêm nhóm con trên thẻ nhóm, hoặc «Thêm Ban/Khối» để tạo team gốc mới.
      </p>
    </div>

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
          Chưa có Ban/Khối nào
        </p>
        <p class="mt-1 max-w-sm text-sm text-slate-500">
          Tạo team gốc đầu tiên để sắp xếp nhân sự theo sơ đồ tổ chức hai cấp.
        </p>
      </div>
      <button
        v-if="can.create"
        type="button"
        class="btn-primary text-sm"
        @click="openCreateRoot()"
      >
        Thêm Ban/Khối đầu tiên
      </button>
    </div>

    <div
      v-else-if="pageMode === 'overview'"
      class="space-y-5"
    >
      <OrgTeamForestSummaryBar :stats="forestStats" />

      <div
        v-if="trees.length > 3"
        class="max-w-xl"
      >
        <DatagridToolbarSearch
          v-model="overviewQuery"
          hide-label
          stretch
          placeholder="Tìm Ban/Khối hoặc trưởng nhóm…"
          aria-label="Tìm team trong tổng quan"
        />
      </div>

      <div>
        <p class="mb-3 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
          Danh sách Ban / Khối
        </p>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
          <OrgTeamRootCard
            v-for="root in filteredOverviewRoots"
            :key="root.id"
            :node="root"
            :active="activeRootId === root.id"
            @select="openChartForRoot"
            @edit="openEdit"
          />
        </div>
        <p
          v-if="!filteredOverviewRoots.length"
          class="py-8 text-center text-sm text-slate-500"
        >
          Không có team nào khớp tìm kiếm.
        </p>
      </div>
    </div>

    <div
      v-else-if="activeRoot"
      class="space-y-4"
    >
      <div
        v-if="trees.length > 1"
        class="flex flex-wrap gap-2"
        role="tablist"
        aria-label="Chọn Ban/Khối"
      >
        <button
          v-for="root in trees"
          :key="root.id"
          type="button"
          role="tab"
          class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
          :class="activeRoot?.id === root.id
            ? 'border-brand/30 bg-brand text-white shadow-sm'
            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
          :aria-selected="activeRoot?.id === root.id"
          @click="activeRootId = root.id"
        >
          {{ root.name }}
        </button>
      </div>

      <OrgTeamChartCanvas
        :key="`${activeRoot.id}-${pageMode}`"
        :title="activeRoot.name"
        :level-label="`Sơ đồ · ${activeRoot.level === 1 ? 'Ban / Khối' : 'Nhóm'}`"
      >
        <ul class="org-tree org-tree--root flex min-w-min justify-center">
          <OrgTeamChart
            :node="activeRoot"
            :edit-mode="pageMode === 'edit'"
            :can-manage="!!can.create"
            @edit="openEdit"
            @add-child="onAddChild"
            @delete="onDelete"
            @select-person="onSelectPerson"
          />
        </ul>
      </OrgTeamChartCanvas>
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
      @close="closePersonDrawer"
    />
  </AppLayout>
</template>
