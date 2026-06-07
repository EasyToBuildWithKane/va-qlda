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
    branchOptions: { type: Array, default: () => [] },
    levelHints: { type: Object, default: () => ({}) },
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
        message: `Xoá «${node.name}» và toàn bộ nhóm con? Hành động không hoàn tác.`,
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
        subtitle="Sơ đồ tổ chức 3 cấp: ban/khối → đội nhóm → nhánh (GVS, phần mềm PB, trợ lý dự án)"
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
            Thêm nhóm gốc
          </button>
        </template>
      </PageHeader>
    </template>

    <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
      <p class="font-medium text-slate-800">
        Gợi ý cấu trúc
      </p>
      <ul class="mt-2 list-inside list-disc space-y-1 text-xs">
        <li>
          <strong>Cấp 1:</strong> Leader khối (vd. Nguyễn Anh Khoa — Leader Phần Mềm)
        </li>
        <li>
          <strong>Cấp 2:</strong> Đội chức năng (vd. Đội ngũ Dev — 3 dev)
        </li>
        <li>
          <strong>Cấp 3:</strong> Tổ nhỏ / nhánh — gán nhánh GVS, phần mềm phòng ban hoặc trợ lý dự án cho từng người
        </li>
      </ul>
    </div>

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
        Chưa có sơ đồ team. Tạo nhóm cấp 1 để bắt đầu.
      </p>
      <button
        v-if="can.create"
        type="button"
        class="btn-primary text-sm"
        @click="openCreate(null)"
      >
        Tạo nhóm đầu tiên
      </button>
    </div>

    <div
      v-else
      class="space-y-8"
    >
      <section
        v-for="(root, idx) in trees"
        :key="root.id"
        class="overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-b from-slate-50/80 to-white shadow-sm"
      >
        <div
          v-if="trees.length > 1"
          class="border-b border-slate-200/80 bg-white/80 px-5 py-2.5"
        >
          <p class="text-xs font-medium text-slate-500">
            Sơ đồ {{ idx + 1 }}
            <span class="text-slate-400">·</span>
            <span class="font-semibold text-slate-700">{{ root.name }}</span>
          </p>
        </div>
        <div class="overflow-x-auto px-4 py-8 sm:px-8">
          <div class="flex min-w-min justify-center">
            <OrgTeamChart
              :node="root"
              :can-manage="!!can.create"
              @edit="openEdit"
              @add-child="onAddChild"
              @delete="onDelete"
            />
          </div>
        </div>
      </section>
    </div>

    <OrgTeamFormModal
      :show="modalOpen"
      :team="editing"
      :parent-options="parentOptions"
      :employees="employees"
      :branch-options="branchOptions"
      :level-hints="levelHints"
      :preset-parent-id="presetParentId"
      @close="modalOpen = false"
      @saved="modalOpen = false"
    />
  </AppLayout>
</template>
