<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import OrgTeamEditWorkspace from '@/modules/people/components/OrgTeamEditWorkspace.vue';

const props = defineProps({
    tree: { type: Object, required: true },
    roots: { type: Array, default: () => [] },
    parentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    branchOptions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const activeRootId = computed(() => props.tree?.id ?? null);

function switchRoot(id) {
    if (id === activeRootId.value) {
        return;
    }
    router.get(`/org-teams/${id}/edit`, {}, { preserveScroll: false });
}
</script>

<template>
  <Head :title="`Chỉnh sửa · ${tree.name}`" />

  <AppLayout>
    <template #header>
      <PageHeader
        title="Chỉnh sửa cấu trúc"
        :subtitle="tree.name"
        icon="edit"
        icon-color="brand"
        back-href="/org-teams"
      >
        <Link
          href="/org-teams"
          class="btn-secondary flex h-9 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="org-teams"
            :size="15"
          />
          Xem sơ đồ
        </Link>
      </PageHeader>
    </template>

    <div class="space-y-4">
      <div
        v-if="roots.length > 1"
        class="flex flex-wrap items-center gap-2"
        role="tablist"
        aria-label="Chọn nhóm gốc"
      >
        <span class="text-xs font-medium text-slate-500">Nhóm gốc:</span>
        <button
          v-for="root in roots"
          :key="root.id"
          type="button"
          role="tab"
          class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
          :class="activeRootId === root.id
            ? 'border-brand/40 bg-brand text-white shadow-sm'
            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
          :aria-selected="activeRootId === root.id"
          @click="switchRoot(root.id)"
        >
          {{ root.name }}
        </button>
      </div>

      <OrgTeamEditWorkspace
        :tree="tree"
        :parent-options="parentOptions"
        :employees="employees"
        :branch-options="branchOptions"
        :can="can"
      />
    </div>
  </AppLayout>
</template>
