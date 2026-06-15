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
        title="Cập nhật sơ đồ tổ chức"
        :subtitle="`Đang chỉnh: ${tree.name}`"
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

    <div class="w-full min-w-0 space-y-4">
      <nav
        v-if="roots.length > 1"
        class="card flex overflow-x-auto p-1"
        role="tablist"
        aria-label="Chọn phòng hoặc ban"
      >
        <button
          v-for="root in roots"
          :key="root.id"
          type="button"
          role="tab"
          class="relative shrink-0 rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors"
          :class="activeRootId === root.id
            ? 'bg-brand text-white shadow-sm'
            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
          :aria-selected="activeRootId === root.id"
          @click="switchRoot(root.id)"
        >
          {{ root.name }}
        </button>
      </nav>

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
