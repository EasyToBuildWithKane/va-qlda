<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import { summarizeSubtree } from '@/modules/people/composables/useOrgTeamTreeStats.js';

const props = defineProps({
    trees: { type: Array, default: () => [] },
    activeId: { type: Number, default: null },
});

const emit = defineEmits(['select', 'back-overview']);

const query = ref('');

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) {
        return props.trees;
    }

    return props.trees.filter((t) => {
        const name = (t.name ?? '').toLowerCase();
        const leader = (t.leader?.name ?? '').toLowerCase();

        return name.includes(q) || leader.includes(q);
    });
});

function peopleCount(node) {
    return summarizeSubtree(node).peopleCount;
}
</script>

<template>
  <aside class="flex w-full shrink-0 flex-col rounded-2xl border border-slate-200/80 bg-white shadow-sm lg:w-72 xl:w-80">
    <div class="border-b border-slate-100 px-4 py-3">
      <button
        type="button"
        class="mb-3 flex items-center gap-1.5 text-xs font-medium text-slate-500 transition-colors hover:text-brand"
        @click="emit('back-overview')"
      >
        <AppIcon
          name="chevron-left"
          :size="14"
        />
        Tổng quan
      </button>
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
        Ban / Khối
      </p>
      <p class="text-sm text-slate-600">
        {{ trees.length }} team
      </p>
    </div>

    <div class="border-b border-slate-100 px-3 py-3">
      <DatagridToolbarSearch
        v-model="query"
        hide-label
        compact
        placeholder="Tìm team hoặc trưởng nhóm…"
        aria-label="Tìm Ban/Khối"
      />
    </div>

    <ul
      class="max-h-[min(28rem,50vh)] flex-1 overflow-y-auto p-2 lg:max-h-none lg:flex-1"
      role="listbox"
      aria-label="Danh sách Ban/Khối"
    >
      <li
        v-for="root in filtered"
        :key="root.id"
      >
        <button
          type="button"
          role="option"
          class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors"
          :class="activeId === root.id
            ? 'bg-brand/10 ring-1 ring-brand/20'
            : 'hover:bg-slate-50'"
          :aria-selected="activeId === root.id"
          @click="emit('select', root.id)"
        >
          <Avatar
            v-if="root.leader"
            :src="root.leader.avatar_path"
            :name="root.leader.name"
            :size="36"
          />
          <span
            v-else
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand/10 text-brand"
          >
            <AppIcon
              name="org-teams"
              :size="16"
            />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-slate-800">
              {{ root.name }}
            </span>
            <span class="block text-xs text-slate-500">
              {{ peopleCount(root) }} thành viên
            </span>
          </span>
          <AppIcon
            v-if="activeId === root.id"
            name="chevron-right"
            :size="14"
            class="shrink-0 text-brand"
          />
        </button>
      </li>
      <li
        v-if="!filtered.length"
        class="px-3 py-6 text-center text-xs text-slate-400"
      >
        Không tìm thấy team phù hợp.
      </li>
    </ul>
  </aside>
</template>
