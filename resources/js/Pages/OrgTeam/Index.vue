<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import OrgOverviewHeader from '@/modules/people/components/OrgOverviewHeader.vue';
import OrgFilterBar from '@/modules/people/components/OrgFilterBar.vue';
import OrgGraph from '@/modules/people/components/OrgGraph.vue';
import OrgTeamChart from '@/modules/people/components/OrgTeamChart.vue';
import OrgTeamChartCanvas from '@/modules/people/components/OrgTeamChartCanvas.vue';
import OrgTeamFormModal from '@/modules/people/components/OrgTeamFormModal.vue';
import OrgTeamPersonDetailDrawer from '@/modules/people/components/OrgTeamPersonDetailDrawer.vue';
import { useDialog } from '@/composables/useDialog';

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
        confirmLabel: 'Xoá',
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
      <OrgOverviewHeader :overview="overview" />

      <div class="flex flex-wrap items-center gap-3">
        <DatagridSegmentedControl
          v-model="pageMode"
          :items="modeItems"
          aria-label="Chế độ trang sơ đồ tổ chức"
        />
        <p
          v-if="pageMode === 'edit' && can.create"
          class="text-[11px] text-slate-500"
        >
          Thêm nhóm con trên thẻ nhóm, hoặc «Thêm Nhóm» để tạo nhóm gốc mới.
        </p>
      </div>

      <!-- Graph mode -->
      <template v-if="pageMode === 'graph'">
        <OrgFilterBar
          v-model="filter"
          :root-options="rootOptions"
        />
        <OrgGraph
          :trees="trees"
          :filter="filter"
          @select-person="openPerson"
          @select-leader="openLeader"
        />
      </template>

      <!-- Edit mode (preserved tree editor) -->
      <div
        v-else-if="activeRoot"
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
