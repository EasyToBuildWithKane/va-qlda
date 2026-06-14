<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import OrgTeamChart from '@/modules/people/components/OrgTeamChart.vue';
import OrgTeamFormModal from '@/modules/people/components/OrgTeamFormModal.vue';
import OrgTeamPersonDetailDrawer from '@/modules/people/components/OrgTeamPersonDetailDrawer.vue';
import OrgTeamRootCard from '@/modules/people/components/OrgTeamRootCard.vue';
import OrgTeamRootSidebar from '@/modules/people/components/OrgTeamRootSidebar.vue';
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

const useSidebarNav = computed(() => props.trees.length >= 2);

const pageSubtitle = computed(() => {
    const n = props.trees.length;
    if (n === 0) {
        return 'Sơ đồ team và thành viên';
    }
    const people = forestStats.value.peopleCount;
    if (pageMode.value === 'overview') {
        return `${n} Ban/Khối · ${people} thành viên trên toàn hệ thống`;
    }
    if (n === 1) {
        return '1 Ban/Khối — có thể thêm nhiều team độc lập';
    }

    return `${n} Ban/Khối — chọn team để xem sơ đồ chi tiết`;
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

function backToOverview() {
    pageMode.value = 'overview';
}
</script>

<template>
  <Head title="Quản lý team" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý team"
        :subtitle="pageSubtitle"
        icon="org-teams"
        icon-color="brand"
        :badge="trees.length > 1 ? trees.length : null"
      >
        <div class="flex shrink-0 flex-wrap items-center gap-2">
          <div
            v-if="trees.length"
            class="flex rounded-btn bg-slate-100 p-0.5"
            role="tablist"
            aria-label="Chế độ trang"
          >
            <button
              type="button"
              role="tab"
              class="rounded px-3 py-1.5 text-xs font-medium transition-colors"
              :class="pageMode === 'overview'
                ? 'bg-white text-slate-800 shadow-sm'
                : 'text-slate-500 hover:text-slate-700'"
              :aria-selected="pageMode === 'overview'"
              @click="pageMode = 'overview'"
            >
              Tổng quan
            </button>
            <button
              type="button"
              role="tab"
              class="rounded px-3 py-1.5 text-xs font-medium transition-colors"
              :class="pageMode === 'chart'
                ? 'bg-white text-slate-800 shadow-sm'
                : 'text-slate-500 hover:text-slate-700'"
              :aria-selected="pageMode === 'chart'"
              @click="pageMode = 'chart'"
            >
              Sơ đồ
            </button>
            <button
              v-if="can.create"
              type="button"
              role="tab"
              class="rounded px-3 py-1.5 text-xs font-medium transition-colors"
              :class="pageMode === 'edit'
                ? 'bg-white text-slate-800 shadow-sm'
                : 'text-slate-500 hover:text-slate-700'"
              :aria-selected="pageMode === 'edit'"
              @click="pageMode = 'edit'"
            >
              Chỉnh sửa
            </button>
          </div>
          <Link
            href="/org-teams/members"
            class="btn-secondary flex items-center gap-1.5 text-sm"
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
            class="btn-primary flex items-center gap-1.5 text-sm"
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
      class="space-y-6"
    >
      <div class="grid gap-3 sm:grid-cols-3">
        <div class="card flex items-center gap-4 px-5 py-4">
          <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand/10 text-brand">
            <AppIcon
              name="org-teams"
              :size="22"
            />
          </span>
          <div>
            <p class="text-xs font-medium text-slate-500">
              Ban / Khối
            </p>
            <p class="font-display text-2xl font-bold tabular-nums text-slate-900">
              {{ forestStats.rootCount }}
            </p>
          </div>
        </div>
        <div class="card flex items-center gap-4 px-5 py-4">
          <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
            <AppIcon
              name="members"
              :size="22"
            />
          </span>
          <div>
            <p class="text-xs font-medium text-slate-500">
              Tổng nhóm (cây)
            </p>
            <p class="font-display text-2xl font-bold tabular-nums text-slate-900">
              {{ forestStats.teamCount }}
            </p>
          </div>
        </div>
        <div class="card flex items-center gap-4 px-5 py-4">
          <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
            <AppIcon
              name="member-profiles"
              :size="22"
            />
          </span>
          <div>
            <p class="text-xs font-medium text-slate-500">
              Thành viên
            </p>
            <p class="font-display text-2xl font-bold tabular-nums text-slate-900">
              {{ forestStats.peopleCount }}
            </p>
          </div>
        </div>
      </div>

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

    <div
      v-else
      class="space-y-4"
    >
      <p
        v-if="pageMode === 'chart'"
        class="text-xs text-slate-500"
      >
        Bấm vào thẻ thành viên để xem chi tiết. Dùng sidebar hoặc tab «Tổng quan» khi có nhiều Ban/Khối.
      </p>
      <p
        v-else-if="can.create"
        class="text-xs text-slate-500"
      >
        Chế độ chỉnh sửa — thêm nhóm con trên thẻ nhóm, hoặc «Thêm Ban/Khối» để tạo team độc lập mới.
      </p>

      <div
        class="flex flex-col gap-4 lg:flex-row lg:items-start"
        :class="{ 'lg:gap-6': useSidebarNav }"
      >
        <OrgTeamRootSidebar
          v-if="useSidebarNav"
          :trees="trees"
          :active-id="activeRoot?.id ?? null"
          @select="activeRootId = $event"
          @back-overview="backToOverview"
        />

        <section
          v-if="activeRoot"
          :key="`${activeRoot.id}-${pageMode}`"
          class="min-w-0 flex-1 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm"
        >
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50/80 to-white px-4 py-3 sm:px-5">
            <div class="min-w-0">
              <p class="text-[10px] font-semibold uppercase tracking-wide text-brand/70">
                {{ activeRoot.level === 1 ? 'Ban / Khối' : 'Nhóm' }}
              </p>
              <p class="font-display truncate text-lg font-bold text-slate-900">
                {{ activeRoot.name }}
              </p>
            </div>
            <div
              v-if="!useSidebarNav && trees.length > 1"
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
          </div>
          <div class="overflow-x-auto px-3 py-6 sm:px-6">
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
          </div>
        </section>
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
      @close="closePersonDrawer"
    />
  </AppLayout>
</template>
