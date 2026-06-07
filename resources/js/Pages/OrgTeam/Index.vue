<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import OrgTeamChart from '@/modules/people/components/OrgTeamChart.vue';
import OrgTeamFormModal from '@/modules/people/components/OrgTeamFormModal.vue';
import { useDialog } from '@/composables/useDialog';

defineProps({
    trees: { type: Array, default: () => [] },
    parentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const modalOpen = ref(false);
const editing = ref(null);
const presetParentId = ref(null);

function openCreate(parentId = null) {
    editing.value = null;
    presetParentId.value = parentId;
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
  <Head title="Quản lý team" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản lý team"
        subtitle="Sơ đồ team và thành viên"
        icon="org-teams"
        icon-color="brand"
      >
        <template
          v-if="can.create"
          #actions
        >
          <button
            type="button"
            class="btn-primary flex items-center gap-1.5 text-sm"
            @click="openCreate(null)"
          >
            <AppIcon
              name="plus"
              :size="15"
            />
            Thêm nhóm
          </button>
        </template>
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
        @click="openCreate(null)"
      >
        Thêm nhóm
      </button>
    </div>

    <div
      v-else
      class="space-y-8"
    >
      <section
        v-for="root in trees"
        :key="root.id"
        class="overflow-hidden rounded-lg border border-slate-200 bg-white"
      >
        <div
          v-if="trees.length > 1"
          class="border-b border-slate-100 px-4 py-2"
        >
          <p class="text-xs text-slate-500">
            {{ root.name }}
          </p>
        </div>
        <div class="overflow-x-auto px-3 py-6 sm:px-6">
          <ul class="org-tree org-tree--root flex min-w-min justify-center">
            <OrgTeamChart
              :node="root"
              :can-manage="!!can.create"
              @edit="openEdit"
              @add-child="onAddChild"
              @delete="onDelete"
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
      @close="modalOpen = false"
      @saved="modalOpen = false"
    />
  </AppLayout>
</template>
