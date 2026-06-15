<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
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

const isCreateMode = computed(() => createChildForParentId.value != null && canManage.value);

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

function tabIsActive(nodeId) {
    return !isCreateMode.value && selectedUnitId.value === nodeId;
}
</script>

<template>
  <div class="w-full min-w-0">
    <div
      v-if="!canManage"
      class="card px-4 py-10 text-center text-sm text-slate-500"
    >
      Bạn chỉ được xem — không có quyền chỉnh sửa.
    </div>

    <div
      v-else
      class="card overflow-hidden"
    >
      <nav
        class="flex items-stretch gap-0 overflow-x-auto border-b border-slate-200 bg-slate-50/90"
        role="tablist"
        aria-label="Chọn đơn vị trên sơ đồ"
      >
        <button
          v-for="{ node, depth } in flatRows"
          :key="node.id"
          type="button"
          role="tab"
          class="relative flex shrink-0 flex-col items-start border-r border-slate-200/80 px-4 py-3 text-left transition-colors last:border-r-0"
          :class="tabIsActive(node.id)
            ? 'bg-white text-brand'
            : 'text-slate-600 hover:bg-white/60 hover:text-slate-900'"
          :aria-selected="tabIsActive(node.id)"
          :style="depth > 0 ? { paddingLeft: `${1 + depth * 0.65}rem` } : undefined"
          @click="selectUnit(node.id)"
        >
          <span
            class="max-w-[12rem] truncate text-sm font-semibold sm:max-w-[14rem]"
            :title="node.name"
          >
            {{ node.name }}
          </span>
          <span class="mt-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">
            {{ depth === 0 ? 'Phòng / ban' : 'Bộ phận' }}
          </span>
          <span
            v-if="tabIsActive(node.id)"
            class="absolute inset-x-0 bottom-0 h-0.5 bg-brand"
            aria-hidden="true"
          />
        </button>

        <button
          v-if="isCreateMode"
          type="button"
          role="tab"
          class="relative flex shrink-0 flex-col items-start border-r border-slate-200/80 bg-brand/5 px-4 py-3 text-left text-brand"
          aria-selected="true"
        >
          <span class="text-sm font-semibold">
            + Bộ phận mới
          </span>
          <span
            v-if="createParentNode"
            class="mt-0.5 max-w-[12rem] truncate text-[10px] text-slate-500"
          >
            Thuộc {{ createParentNode.name }}
          </span>
          <span
            class="absolute inset-x-0 bottom-0 h-0.5 bg-brand"
            aria-hidden="true"
          />
        </button>
      </nav>

      <div
        v-if="isCreateMode"
        class="border-b border-slate-100 bg-white px-4 py-3 sm:px-6"
      >
        <button
          type="button"
          class="flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-brand"
          @click="cancelCreate"
        >
          <AppIcon
            name="chevron-left"
            :size="14"
          />
          Quay lại tab trước
        </button>
      </div>

      <div
        v-else-if="selectedRow"
        class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-white px-4 py-3 sm:px-6"
      >
        <div class="min-w-0">
          <p
            v-if="selectedBreadcrumb.length > 1"
            class="text-xs text-slate-500"
          >
            {{ selectedBreadcrumb.join(' › ') }}
          </p>
          <p class="text-sm font-medium text-slate-800">
            {{ orgTeamFriendlyLevelLabel(selectedRow.node) }}
          </p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
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
            class="btn-ghost flex h-9 items-center gap-1 px-2 text-xs text-rose-600 hover:bg-rose-50"
            @click="deleteTeam(selectedRow.node)"
          >
            <AppIcon
              name="delete"
              :size="14"
            />
            {{ selectedRow.node.level === 1 ? 'Xóa sơ đồ' : 'Xóa' }}
          </button>
        </div>
      </div>

      <div class="bg-white px-4 py-5 sm:px-6 sm:py-6">
        <OrgTeamTeamForm
          v-if="isCreateMode"
          :parent-options="parentOptions"
          :employees="employees"
          :branch-options="branchOptions"
          :preset-parent-id="createChildForParentId"
          show-cancel
          @saved="onChildCreated"
          @cancel="cancelCreate"
        />
        <OrgTeamTeamForm
          v-else-if="selectedRow"
          :team="selectedRow.node"
          :parent-options="parentOptions"
          :employees="employees"
          :branch-options="branchOptions"
          :unit-display-name="selectedRow.node.name"
        />
      </div>
    </div>
  </div>
</template>
