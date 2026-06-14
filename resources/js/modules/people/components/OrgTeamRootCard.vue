<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { summarizeSubtree } from '@/modules/people/composables/useOrgTeamTreeStats.js';

const props = defineProps({
    node: { type: Object, required: true },
    active: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'edit']);

const stats = computed(() => summarizeSubtree(props.node));

const leader = computed(() => props.node.leader ?? null);

const subLabel = computed(() => {
    const n = stats.value.subGroupCount;
    if (n === 0) {
        return 'Không có nhóm con';
    }
    if (n === 1) {
        return '1 nhóm con trực tiếp';
    }

    return `${n} nhóm con trực tiếp`;
});
</script>

<template>
  <article
    class="group flex h-full flex-col overflow-hidden rounded-card border bg-white shadow-sm transition-all duration-200"
    :class="active
      ? 'border-brand/35 ring-1 ring-brand/20'
      : 'border-slate-200/80 hover:border-slate-300 hover:shadow-md'"
  >
    <header class="border-b border-slate-100 px-5 py-4">
      <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
        Ban / Khối
      </p>
      <h3 class="mt-1 font-display text-lg font-semibold leading-snug text-slate-900">
        {{ node.name }}
      </h3>
      <p
        v-if="node.is_active === false"
        class="mt-2 inline-flex rounded-md border border-slate-200 px-2 py-0.5 text-[10px] font-medium text-slate-500"
      >
        Ngưng hoạt động
      </p>
    </header>

    <div class="flex flex-1 flex-col gap-4 px-5 py-4">
      <div
        v-if="leader"
        class="flex items-center gap-3"
      >
        <Avatar
          :src="leader.avatar_path"
          :name="leader.name"
          :size="40"
        />
        <div class="min-w-0 flex-1">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Người Quản Lý
          </p>
          <p class="truncate text-sm font-semibold text-slate-800">
            {{ leader.name }}
          </p>
          <p
            v-if="leader.role_title"
            class="truncate text-xs text-slate-500"
          >
            {{ leader.role_title }}
          </p>
        </div>
      </div>
      <p
        v-else
        class="text-xs text-slate-400"
      >
        Chưa gán trưởng nhóm
      </p>

      <dl class="grid grid-cols-3 divide-x divide-slate-100 rounded-lg border border-slate-100">
        <div class="px-2 py-2.5 text-center">
          <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
            Nhóm
          </dt>
          <dd class="mt-0.5 font-display text-xl font-bold tabular-nums text-slate-900">
            {{ stats.teamCount }}
          </dd>
          <dd class="mt-0.5 text-[10px] text-slate-500">
            mọi cấp
          </dd>
        </div>
        <div class="px-2 py-2.5 text-center">
          <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
            Thành viên
          </dt>
          <dd class="mt-0.5 font-display text-xl font-bold tabular-nums text-slate-900">
            {{ stats.peopleCount }}
          </dd>
        </div>
        <div class="px-2 py-2.5 text-center">
          <dt class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
            Cấp con
          </dt>
          <dd class="mt-1 text-[11px] font-medium leading-snug text-slate-600">
            {{ subLabel }}
          </dd>
        </div>
      </dl>
    </div>

    <footer class="mt-auto flex gap-2 border-t border-slate-100 px-4 py-3">
      <button
        type="button"
        class="btn-primary flex flex-1 items-center justify-center gap-1.5 text-sm"
        @click="emit('select', node)"
      >
        <AppIcon
          name="org-teams"
          :size="15"
        />
        Xem sơ đồ
      </button>
      <button
        v-if="node.can?.update"
        type="button"
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-btn border border-slate-200 text-slate-500 transition-colors hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700"
        aria-label="Sửa Ban/Khối"
        @click="emit('edit', node)"
      >
        <AppIcon
          name="edit"
          :size="16"
        />
      </button>
    </footer>
  </article>
</template>
