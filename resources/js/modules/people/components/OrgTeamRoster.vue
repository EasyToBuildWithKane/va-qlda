<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    leader: { type: Object, default: null },
    members: { type: Array, default: () => [] },
    level: { type: Number, default: 1 },
});

/** Thành viên danh sách (không trùng trưởng nhóm). */
const roster = computed(() => {
    const list = props.members ?? [];
    const leaderId = props.leader?.id;
    if (!leaderId) {
        return list;
    }

    return list.filter((m) => m.employee?.id !== leaderId);
});

</script>

<template>
  <div class="space-y-3">
    <!-- Trưởng nhóm -->
    <div
      v-if="leader"
      class="flex items-center gap-3 rounded-xl border border-brand/15 bg-gradient-to-r from-brand/[0.06] to-white p-3 shadow-sm"
    >
      <div class="relative shrink-0">
        <Avatar
          :src="leader.avatar_path"
          :name="leader.name"
          :size="44"
        />
        <span
          class="absolute -bottom-0.5 -right-0.5 grid h-5 w-5 place-items-center rounded-full bg-brand text-white shadow ring-2 ring-white"
          title="Trưởng nhóm"
        >
          <AppIcon
            name="star"
            :size="11"
            :stroke-width="2.25"
          />
        </span>
      </div>
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-slate-900">
          {{ leader.name }}
        </p>
        <Badge
          label="Trưởng nhóm"
          color="brand"
        />
      </div>
    </div>

    <!-- Thành viên -->
    <div
      v-if="roster.length"
      class="rounded-xl border border-slate-200/80 bg-slate-50/90 p-3"
    >
      <div class="mb-2.5 flex items-center justify-between gap-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
          Thành viên
        </p>
        <span class="rounded-full bg-white px-2 py-0.5 text-[11px] font-medium text-slate-600 shadow-sm ring-1 ring-slate-200/80">
          {{ roster.length }}
        </span>
      </div>
      <ul
        class="space-y-2"
        :class="level === 3 ? 'grid gap-2 sm:grid-cols-1' : ''"
      >
        <li
          v-for="m in roster"
          :key="m.id"
          class="flex items-center gap-3 rounded-lg border border-white bg-white px-2.5 py-2.5 shadow-sm ring-1 ring-slate-100"
        >
          <div class="shrink-0 rounded-full ring-2 ring-slate-100">
            <Avatar
              :src="m.employee?.avatar_path"
              :name="m.employee?.name"
              :size="40"
            />
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-slate-800">
              {{ m.employee?.name || '—' }}
            </p>
            <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
              <Badge
                v-if="m.branch?.label"
                :label="m.branch.label"
                :color="m.branch.color || 'slate'"
              />
              <span
                v-else
                class="text-[11px] text-slate-500"
              >Thành viên nhóm</span>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <!-- Chỉ trưởng, không thành viên khác -->
    <p
      v-else-if="leader && !roster.length"
      class="rounded-lg border border-dashed border-slate-200 bg-white/60 px-3 py-2 text-center text-[11px] text-slate-500"
    >
      Chưa có thành viên khác trong nhóm
    </p>

    <!-- Không trưởng, không thành viên -->
    <div
      v-else-if="!leader && !roster.length"
      class="flex items-center gap-2 rounded-lg border border-dashed border-slate-200 bg-slate-50/50 px-3 py-3 text-xs text-slate-500"
    >
      <AppIcon
        name="members"
        :size="18"
        class="shrink-0 text-slate-400"
      />
      <span>Chưa gán trưởng nhóm hoặc thành viên</span>
    </div>
  </div>
</template>
