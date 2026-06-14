<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import OrgTeamChart from '@/modules/people/components/OrgTeamChart.vue';
import OrgTeamFormModal from '@/modules/people/components/OrgTeamFormModal.vue';
import OrgTeamPersonDetailDrawer from '@/modules/people/components/OrgTeamPersonDetailDrawer.vue';
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
const pageMode = ref('view');
const activeRootId = ref(null);
const selectedPerson = ref(null);
const personDrawerOpen = ref(false);

const pageSubtitle = computed(() => {
    const n = props.trees.length;
    if (n === 0) {
        return 'Sơ đồ team và thành viên';
    }
    if (n === 1) {
        return '1 Ban/Khối — có thể thêm nhiều team độc lập';
    }

    return `${n} Ban/Khối — chọn tab để xem từng sơ đồ`;
});

const activeRoot = computed(() => {
    if (!props.trees.length) {
        return null;
    }
    const id = activeRootId.value;
    const match = props.trees.find((t) => t.id === id);

    return match ?? props.trees[0];
});

watch(
    () => props.trees,
    (trees) => {
        if (!trees.length) {
            activeRootId.value = null;

            return;
        }
        if (pendingSelectNewRoot.value) {
            const newest = [...trees].sort((a, b) => b.id - a.id)[0];
            activeRootId.value = newest?.id ?? trees[0].id;
            pendingSelectNewRoot.value = false;

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
              :class="pageMode === 'view'
                ? 'bg-white text-slate-800 shadow-sm'
                : 'text-slate-500 hover:text-slate-700'"
              :aria-selected="pageMode === 'view'"
              @click="pageMode = 'view'"
            >
              Xem sơ đồ
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
      class="card flex flex-col items-center justify-center gap-3 py-16 text-center"
    >
      <AppIcon
        name="org-teams"
        :size="40"
        class="text-slate-300"
      />
      <p class="text-sm text-slate-600">
        Chưa có team nào.
      </p>
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
      v-else
      class="space-y-8"
    >
      <p
        v-if="pageMode === 'view'"
        class="text-xs text-slate-500"
      >
        Bấm vào thẻ thành viên để xem chi tiết.
      </p>
      <p
        v-else-if="can.create"
        class="text-xs text-slate-500"
      >
        Chế độ chỉnh sửa — thêm nhóm con trên thẻ nhóm, hoặc «Thêm Ban/Khối» để tạo team độc lập mới.
      </p>

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
            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-800'"
          :aria-selected="activeRoot?.id === root.id"
          @click="activeRootId = root.id"
        >
          {{ root.name }}
        </button>
      </div>

      <section
        v-if="activeRoot"
        :key="activeRoot.id"
        class="overflow-hidden rounded-lg border border-slate-200 bg-white"
      >
        <div class="border-b border-slate-100 px-4 py-2.5">
          <p class="text-sm font-semibold text-slate-800">
            {{ activeRoot.name }}
          </p>
          <p
            v-if="trees.length > 1"
            class="mt-0.5 text-xs text-slate-500"
          >
            Team {{ trees.findIndex((t) => t.id === activeRoot.id) + 1 }} / {{ trees.length }}
          </p>
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
