<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import OrgTeamRoster from '@/modules/people/components/OrgTeamRoster.vue';

defineProps({
    node: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'add-child', 'delete']);
</script>

<template>
  <div class="flex min-w-[14rem] max-w-sm flex-col items-center">
    <article
      class="w-full overflow-hidden rounded-lg border border-slate-200 bg-white"
    >
      <header class="border-b border-slate-100 bg-slate-50/80 px-3 py-2">
        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">
          {{ node.level_label }}
        </p>
        <h3 class="mt-0.5 text-sm font-semibold leading-snug text-slate-900">
          {{ node.name }}
        </h3>
      </header>

      <div class="px-3 py-2.5">
        <OrgTeamRoster
          :leader="node.leader"
          :members="node.members"
        />

        <div
          v-if="canManage && node.can?.update"
          class="mt-2 flex flex-wrap gap-1 border-t border-slate-100 pt-2"
        >
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded border border-slate-200 px-2 py-1 text-[10px] font-medium text-slate-600 hover:bg-slate-50"
            @click="emit('edit', node)"
          >
            <AppIcon
              name="edit"
              :size="12"
            />
            Sửa
          </button>
          <button
            v-if="node.level < 3"
            type="button"
            class="inline-flex items-center gap-1 rounded border border-slate-200 px-2 py-1 text-[10px] font-medium text-slate-600 hover:bg-slate-50"
            @click="emit('add-child', node)"
          >
            <AppIcon
              name="plus"
              :size="12"
            />
            Nhóm con
          </button>
          <button
            v-if="node.can?.delete"
            type="button"
            class="inline-flex items-center gap-1 rounded border border-slate-200 px-2 py-1 text-[10px] font-medium text-slate-600 hover:bg-rose-50 hover:text-rose-700"
            @click="emit('delete', node)"
          >
            <AppIcon
              name="delete"
              :size="12"
            />
            Xoá
          </button>
        </div>
      </div>
    </article>

    <div
      v-if="node.children?.length"
      class="relative mt-0 flex w-full flex-col items-center"
    >
      <div
        class="h-5 w-px bg-slate-300"
        aria-hidden="true"
      />

      <div
        class="relative flex w-full flex-wrap items-start justify-center gap-x-4 gap-y-6 px-1 pb-1"
      >
        <div
          v-if="node.children.length > 1"
          class="pointer-events-none absolute top-0 left-[8%] right-[8%] h-px bg-slate-300"
          aria-hidden="true"
        />
        <div
          v-for="child in node.children"
          :key="child.id"
          class="relative flex flex-col items-center"
        >
          <div
            class="h-4 w-px bg-slate-300"
            aria-hidden="true"
          />
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
