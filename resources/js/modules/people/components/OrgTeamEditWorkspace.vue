<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import OrgTeamTeamForm from '@/modules/people/components/OrgTeamTeamForm.vue';
import { flattenOrgTeamTree } from '@/modules/people/composables/useOrgTeamForm.js';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    tree: { type: Object, required: true },
    parentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    branchOptions: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();

const showGuide = ref(true);
const activeNavId = ref(null);
const createChildForParentId = ref(null);

const flatRows = computed(() => flattenOrgTeamTree(props.tree));
const canManage = computed(() => !!props.can.manage);

function scrollToTeam(id) {
    activeNavId.value = id;
    const el = document.getElementById(`org-team-edit-${id}`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function startCreateChild(parentId) {
    createChildForParentId.value = parentId;
    setTimeout(() => {
        const el = document.getElementById(`org-team-create-${parentId}`);
        el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 50);
}

function onChildCreated() {
    createChildForParentId.value = null;
}

async function deleteTeam(node) {
    const ok = await dialog.confirm({
        title: 'Xoá nhóm',
        message: `Xoá «${node.name}» và các nhóm bên trong? Không thể hoàn tác.`,
        confirmLabel: 'Xóa',
        variant: 'danger',
    });
    if (!ok) {
        return;
    }
    router.delete(`/org-teams/${node.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (node.id === props.tree.id) {
                router.visit('/org-teams');
            }
        },
    });
}

function navIndent(depth) {
    return { paddingLeft: `${depth * 0.65}rem` };
}
</script>

<template>
  <div class="space-y-4">
    <div
      v-if="showGuide"
      class="rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white px-4 py-4 shadow-sm sm:px-5"
    >
      <div class="flex items-start justify-between gap-3">
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
            Quy trình Phòng Công nghệ
          </p>
          <ol class="mt-2 list-decimal space-y-1 pl-4 text-sm leading-relaxed text-slate-600">
            <li>
              <strong class="font-semibold text-slate-800">Nhóm chính</strong> — quản lý = GĐ CNTT kiêm Trưởng phòng.
            </li>
            <li>
              Bấm <strong class="font-semibold text-slate-800">Mẫu Phòng CNTT</strong> hoặc thêm nhánh
              «Trưởng ban CNTT», «Phó Phòng Công nghệ» và gán người.
            </li>
            <li>
              <strong class="font-semibold text-slate-800">Nhóm con</strong> Phần mềm &amp; Phần cứng — quản lý + thành viên ở mục «Thành viên khác».
            </li>
            <li>
              Lưu từng khối, sau đó <strong class="font-semibold text-slate-800">Xem sơ đồ</strong> để kiểm tra.
            </li>
          </ol>
        </div>
        <button
          type="button"
          class="shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          aria-label="Ẩn gợi ý"
          @click="showGuide = false"
        >
          <AppIcon
            name="close"
            :size="16"
          />
        </button>
      </div>
    </div>

    <div class="lg:grid lg:grid-cols-12 lg:gap-6">
      <aside class="mb-4 lg:col-span-3 lg:mb-0">
        <nav
          class="rounded-xl border border-slate-200/90 bg-white p-3 shadow-sm lg:sticky lg:top-4"
          aria-label="Danh sách nhóm trong cấu trúc"
        >
          <p class="mb-2 px-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Mục lục
          </p>
          <ul class="max-h-[min(70vh,28rem)] space-y-0.5 overflow-y-auto">
            <li
              v-for="{ node, depth } in flatRows"
              :key="node.id"
            >
              <button
                type="button"
                class="flex w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-left text-xs transition-colors"
                :class="activeNavId === node.id
                  ? 'bg-brand/10 font-semibold text-brand'
                  : 'text-slate-600 hover:bg-slate-50'"
                :style="navIndent(depth)"
                @click="scrollToTeam(node.id)"
              >
                <AppIcon
                  :name="depth === 0 ? 'org-teams' : 'members'"
                  :size="14"
                  class="shrink-0 opacity-70"
                />
                <span class="truncate">{{ node.name }}</span>
              </button>
            </li>
          </ul>
          <button
            v-if="canManage && tree.level < 2"
            type="button"
            class="btn-ghost mt-2 flex h-9 w-full items-center justify-center gap-1 text-xs"
            @click="startCreateChild(tree.id)"
          >
            <AppIcon
              name="plus"
              :size="14"
            />
            Thêm nhóm con
          </button>
        </nav>
      </aside>

      <div class="space-y-6 lg:col-span-9">
        <section
          v-for="{ node, depth } in flatRows"
          :id="`org-team-edit-${node.id}`"
          :key="node.id"
          class="scroll-mt-24 overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm"
          :class="depth === 0 ? 'ring-1 ring-brand/10' : ''"
        >
          <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-5">
            <div class="min-w-0">
              <h2 class="font-display text-base font-semibold text-slate-900">
                {{ node.name }}
              </h2>
              <p class="text-xs text-slate-500">
                {{ node.level_label || (node.level === 1 ? 'Nhóm chính' : 'Nhóm con') }}
                <span v-if="node.leader?.name"> · Quản lý: {{ node.leader.name }}</span>
              </p>
            </div>
            <button
              v-if="canManage && node.can?.delete"
              type="button"
              class="btn-ghost flex h-8 items-center gap-1 px-2 text-xs text-rose-600 hover:bg-rose-50"
              @click="deleteTeam(node)"
            >
              <AppIcon
                name="delete"
                :size="14"
              />
              Xóa nhóm
            </button>
          </header>

          <div
            v-if="canManage"
            class="px-4 py-4 sm:px-5"
          >
            <OrgTeamTeamForm
              :team="node"
              :parent-options="parentOptions"
              :employees="employees"
              :branch-options="branchOptions"
            />
          </div>
          <p
            v-else
            class="px-4 py-6 text-sm text-slate-500 sm:px-5"
          >
            Bạn không có quyền chỉnh sửa nhóm này.
          </p>

          <footer
            v-if="canManage && node.level < 2"
            class="flex flex-wrap gap-2 border-t border-slate-100 bg-slate-50/40 px-4 py-3 sm:px-5"
          >
            <button
              type="button"
              class="btn-secondary flex h-8 items-center gap-1 px-3 text-xs"
              @click="startCreateChild(node.id)"
            >
              <AppIcon
                name="plus"
                :size="14"
              />
              Thêm nhóm con dưới «{{ node.name }}»
            </button>
          </footer>
        </section>

        <section
          v-if="createChildForParentId != null && canManage"
          :id="`org-team-create-${createChildForParentId}`"
          class="scroll-mt-24 overflow-hidden rounded-xl border-2 border-dashed border-brand/30 bg-brand/[0.02] shadow-sm"
        >
          <header class="border-b border-brand/10 px-4 py-3 sm:px-5">
            <h2 class="font-display text-sm font-semibold text-brand">
              Tạo nhóm con mới
            </h2>
          </header>
          <div class="px-4 py-4 sm:px-5">
            <OrgTeamTeamForm
              :parent-options="parentOptions"
              :employees="employees"
              :branch-options="branchOptions"
              :preset-parent-id="createChildForParentId"
              show-cancel
              @saved="onChildCreated"
              @cancel="createChildForParentId = null"
            />
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
