<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import OrgTeamEditGuide from '@/modules/people/components/OrgTeamEditGuide.vue';
import OrgTeamTeamForm from '@/modules/people/components/OrgTeamTeamForm.vue';
import {
    flattenOrgTeamTree,
    orgTeamBreadcrumbNames,
    orgTeamFriendlyLevelLabel,
} from '@/modules/people/composables/useOrgTeamForm.js';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    tree: { type: Object, required: true },
    parentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    branchOptions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();

const selectedUnitId = ref(props.tree?.id ?? null);
const createChildForParentId = ref(null);

const flatRows = computed(() => flattenOrgTeamTree(props.tree));
const canManage = computed(() => !!props.can.manage);

const selectedRow = computed(() => flatRows.value.find(({ node }) => node.id === selectedUnitId.value) ?? null);

const selectedBreadcrumb = computed(() => {
    if (!selectedUnitId.value) {
        return [];
    }

    return orgTeamBreadcrumbNames(selectedUnitId.value, flatRows.value);
});

const createParentNode = computed(() => {
    if (createChildForParentId.value == null) {
        return null;
    }

    return flatRows.value.find(({ node }) => node.id === createChildForParentId.value)?.node ?? null;
});

watch(
    () => props.tree?.id,
    (id) => {
        if (id != null) {
            selectedUnitId.value = id;
            createChildForParentId.value = null;
        }
    },
);

function selectUnit(id) {
    createChildForParentId.value = null;
    selectedUnitId.value = id;
}

function startCreateChild(parentId) {
    createChildForParentId.value = parentId;
    selectedUnitId.value = null;
    setTimeout(() => {
        document.getElementById('org-team-create-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 50);
}

function cancelCreate() {
    createChildForParentId.value = null;
    if (createParentNode.value?.id) {
        selectedUnitId.value = createParentNode.value.id;
    } else {
        selectedUnitId.value = props.tree?.id ?? null;
    }
}

function onChildCreated() {
    createChildForParentId.value = null;
    selectedUnitId.value = props.tree?.id ?? null;
}

async function deleteTeam(node) {
    const isRoot = node.level === 1;
    const ok = await dialog.confirm({
        title: isRoot ? 'Xoá toàn bộ sơ đồ này?' : 'Xoá bộ phận này?',
        message: isRoot
            ? `Mọi bộ phận con trong «${node.name}» cũng sẽ bị xóa. Không thể hoàn tác.`
            : `Xóa «${node.name}» khỏi sơ đồ? Không thể hoàn tác.`,
        confirmLabel: 'Xóa',
        variant: 'danger',
    });
    if (!ok) {
        return;
    }
    router.delete(`/org-teams/${node.id}`, {
        preserveScroll: false,
    });
}

function unitOptionLabel({ node, depth }) {
    const prefix = depth > 0 ? `${'— '.repeat(depth)}` : '';

    return `${prefix}${node.name}`;
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-5">
    <OrgTeamEditGuide />

    <div
      v-if="canManage"
      class="rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm sm:p-5"
    >
      <label
        for="org-team-unit-picker"
        class="text-sm font-semibold text-slate-900"
      >
        Chọn đơn vị cần chỉnh
      </label>
      <p class="mt-0.5 text-xs text-slate-500">
        Mỗi dòng là một ô trên sơ đồ — chọn một, sửa và lưu.
      </p>
      <select
        id="org-team-unit-picker"
        :value="createChildForParentId != null ? '' : selectedUnitId"
        class="input mt-3 w-full text-sm"
        :disabled="createChildForParentId != null"
        @change="selectUnit(Number($event.target.value))"
      >
        <option
          v-for="{ node, depth } in flatRows"
          :key="node.id"
          :value="node.id"
        >
          {{ unitOptionLabel({ node, depth }) }}
        </option>
      </select>

      <div
        v-if="flatRows.length <= 10"
        class="mt-3 hidden flex-wrap gap-2 sm:flex"
      >
        <button
          v-for="{ node, depth } in flatRows"
          :key="`chip-${node.id}`"
          type="button"
          class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors"
          :class="selectedUnitId === node.id && createChildForParentId == null
            ? 'border-brand/40 bg-brand/10 text-brand'
            : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300 hover:bg-white'"
          :style="depth ? { marginLeft: `${Math.min(depth, 3) * 0.5}rem` } : undefined"
          @click="selectUnit(node.id)"
        >
          {{ node.name }}
        </button>
      </div>
    </div>

    <section
      v-if="createChildForParentId != null && canManage"
      id="org-team-create-panel"
      class="scroll-mt-24 overflow-hidden rounded-xl border-2 border-dashed border-brand/35 bg-brand/[0.03] shadow-sm"
    >
      <header class="border-b border-brand/15 bg-white/80 px-4 py-4 sm:px-5">
        <button
          type="button"
          class="mb-2 flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-brand"
          @click="cancelCreate"
        >
          <AppIcon
            name="chevron-left"
            :size="14"
          />
          Quay lại
        </button>
        <h2 class="font-display text-base font-semibold text-slate-900">
          Thêm bộ phận mới
        </h2>
        <p
          v-if="createParentNode"
          class="mt-1 text-sm text-slate-600"
        >
          Thuộc: <span class="font-medium text-slate-800">{{ createParentNode.name }}</span>
        </p>
      </header>
      <div class="px-4 py-4 sm:px-5">
        <OrgTeamTeamForm
          :parent-options="parentOptions"
          :employees="employees"
          :branch-options="branchOptions"
          :preset-parent-id="createChildForParentId"
          show-cancel
          @saved="onChildCreated"
          @cancel="cancelCreate"
        />
      </div>
    </section>

    <section
      v-else-if="selectedRow && canManage"
      class="scroll-mt-24 overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm"
    >
      <header class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-4 py-4 sm:px-5">
        <p
          v-if="selectedBreadcrumb.length > 1"
          class="text-xs text-slate-500"
        >
          {{ selectedBreadcrumb.slice(0, -1).join(' › ') }}
        </p>
        <h2 class="font-display text-lg font-semibold text-slate-900">
          {{ selectedRow.node.name }}
        </h2>
        <p class="mt-0.5 text-sm text-slate-500">
          {{ orgTeamFriendlyLevelLabel(selectedRow.node) }}
        </p>
      </header>

      <div class="px-4 py-4 sm:px-5">
        <OrgTeamTeamForm
          :team="selectedRow.node"
          :parent-options="parentOptions"
          :employees="employees"
          :branch-options="branchOptions"
          :unit-display-name="selectedRow.node.name"
        />
      </div>

      <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/50 px-4 py-3 sm:px-5">
        <button
          v-if="selectedRow.node.level < 2"
          type="button"
          class="btn-secondary flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="startCreateChild(selectedRow.node.id)"
        >
          <AppIcon
            name="plus"
            :size="15"
          />
          Thêm bộ phận con
        </button>
        <button
          v-if="selectedRow.node.can?.delete"
          type="button"
          class="btn-ghost ml-auto flex h-9 items-center gap-1 px-2 text-xs text-rose-600 hover:bg-rose-50"
          @click="deleteTeam(selectedRow.node)"
        >
          <AppIcon
            name="delete"
            :size="14"
          />
          {{ selectedRow.node.level === 1 ? 'Xóa cả sơ đồ' : 'Xóa bộ phận' }}
        </button>
      </footer>
    </section>

    <p
      v-else-if="!canManage"
      class="rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500"
    >
      Bạn chỉ được xem — không có quyền chỉnh sửa.
    </p>
  </div>
</template>
