<script setup>
import Avatar from '@/shared/ui/Avatar.vue';

defineProps({
    node: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'add-child', 'delete']);

const levelRing = {
    1: 'ring-brand/30 border-brand/20',
    2: 'ring-sky-200 border-sky-100',
    3: 'ring-violet-200 border-violet-100',
};

function memberLine(m) {
    if (!m.branch?.label) return m.employee?.name;
    return `${m.employee?.name} · ${m.branch.label}`;
}
</script>

<template>
  <div class="flex min-w-0 flex-col items-center">
    <div
      class="card relative w-full max-w-xs border p-4 ring-2"
      :class="levelRing[node.level] || 'ring-slate-200'"
    >
      <div class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
        {{ node.level_label }}
      </div>
      <h3 class="font-display text-base font-semibold text-slate-900">
        {{ node.name }}
      </h3>
      <div
        v-if="node.leader"
        class="mt-3 flex items-center gap-2"
      >
        <Avatar
          :src="node.leader.avatar_path"
          :name="node.leader.name"
          :size="28"
        />
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-slate-800">
            {{ node.leader.name }}
          </p>
          <p class="text-[11px] text-slate-500">
            Trưởng nhóm
          </p>
        </div>
      </div>
      <ul
        v-if="node.members?.length"
        class="mt-3 space-y-1 border-t border-slate-100 pt-2 text-xs text-slate-600"
      >
        <li
          v-for="m in node.members"
          :key="m.id"
          class="flex items-center gap-1.5"
        >
          <Avatar
            :src="m.employee?.avatar_path"
            :name="m.employee?.name"
            :size="22"
          />
          <span class="truncate">{{ memberLine(m) }}</span>
        </li>
      </ul>
      <div
        v-if="canManage && node.can?.update"
        class="mt-3 flex flex-wrap gap-1 border-t border-slate-100 pt-2"
      >
        <button
          type="button"
          class="rounded-md px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-100"
          @click="emit('edit', node)"
        >
          Sửa
        </button>
        <button
          v-if="node.level < 3"
          type="button"
          class="rounded-md px-2 py-1 text-[11px] font-medium text-brand hover:bg-brand/5"
          @click="emit('add-child', node)"
        >
          + Nhóm con
        </button>
        <button
          v-if="node.can?.delete"
          type="button"
          class="rounded-md px-2 py-1 text-[11px] font-medium text-rose-600 hover:bg-rose-50"
          @click="emit('delete', node)"
        >
          Xoá
        </button>
      </div>
    </div>

    <div
      v-if="node.children?.length"
      class="mt-4 flex w-full flex-col items-center"
    >
      <div class="h-6 w-px bg-slate-200" />
      <div
        class="flex w-full flex-wrap items-start justify-center gap-6"
        :class="node.children.length > 1 ? 'pt-2' : ''"
      >
        <div
          v-for="child in node.children"
          :key="child.id"
          class="flex flex-col items-center"
        >
          <div class="h-4 w-px bg-slate-200" />
          <OrgTeamChart
            :node="child"
            :can-manage="canManage"
            @edit="emit('edit', $event)"
            @add-child="emit('add-child', $event)"
            @delete="emit('delete', $event)"
          />
        </div>
      </div>
    </div>
  </div>
</template>
