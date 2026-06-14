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
        return '1 nhóm con';
    }

    return `${n} nhóm con`;
});
</script>

<template>
  <article
    class="group relative flex h-full flex-col overflow-hidden rounded-2xl border bg-white shadow-sm transition-all duration-200"
    :class="active
      ? 'border-brand/40 ring-2 ring-brand/15 shadow-md'
      : 'border-slate-200/80 hover:border-brand/25 hover:shadow-md'"
  >
    <div class="relative bg-gradient-to-br from-brand via-[#8a0030] to-[#660026] px-5 pb-4 pt-5 text-white">
      <div
        class="pointer-events-none absolute -right-6 -top-8 h-28 w-28 rounded-full bg-white/10 blur-2xl"
        aria-hidden="true"
      />
      <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-white/70">
        Ban / Khối
      </p>
      <h3 class="mt-1 font-display text-lg font-bold leading-snug text-white">
        {{ node.name }}
      </h3>
      <p
        v-if="node.is_active === false"
        class="mt-2 inline-flex rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-medium text-white/90"
      >
        Ngưng hoạt động
      </p>
    </div>

    <div class="flex flex-1 flex-col gap-4 px-5 py-4">
      <div
        v-if="leader"
        class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-2.5"
      >
        <Avatar
          :src="leader.avatar_path"
          :name="leader.name"
          :size="40"
        />
        <div class="min-w-0 flex-1">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-brand/80">
            Trưởng Ban/Khối
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
        class="rounded-xl border border-dashed border-slate-200 px-3 py-3 text-center text-xs text-slate-400"
      >
        Chưa gán trưởng nhóm
      </p>

      <dl class="grid grid-cols-3 gap-2">
        <div class="rounded-xl bg-brand/5 px-2 py-2.5 text-center">
          <dt class="text-[10px] font-medium text-slate-500">
            Nhóm
          </dt>
          <dd class="mt-0.5 font-display text-lg font-bold tabular-nums text-brand">
            {{ stats.teamCount }}
          </dd>
        </div>
        <div class="rounded-xl bg-slate-50 px-2 py-2.5 text-center">
          <dt class="text-[10px] font-medium text-slate-500">
            Thành viên
          </dt>
          <dd class="mt-0.5 font-display text-lg font-bold tabular-nums text-slate-800">
            {{ stats.peopleCount }}
          </dd>
        </div>
        <div class="rounded-xl bg-slate-50 px-2 py-2.5 text-center">
          <dt class="text-[10px] font-medium text-slate-500">
            Cấp con
          </dt>
          <dd class="mt-0.5 text-xs font-semibold leading-tight text-slate-700">
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
