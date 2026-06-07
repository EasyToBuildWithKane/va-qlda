<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    leader: { type: Object, default: null },
    members: { type: Array, default: () => [] },
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
  <div class="space-y-2 text-xs">
    <div
      v-if="leader"
      class="flex gap-2 border-b border-slate-100 pb-2"
    >
      <Avatar
        :src="leader.avatar_path"
        :name="leader.name"
        :size="28"
        class="shrink-0"
      />
      <div class="min-w-0 flex-1">
        <p class="font-medium leading-snug text-slate-900 break-words">
          {{ leader.name }}
        </p>
        <p class="mt-0.5 text-[10px] text-slate-500">
          Trưởng nhóm
        </p>
      </div>
    </div>

    <div v-if="roster.length">
      <p class="mb-1 text-[10px] font-medium uppercase tracking-wide text-slate-400">
        Thành viên ({{ roster.length }})
      </p>
      <ul class="space-y-1.5">
        <li
          v-for="m in roster"
          :key="m.id"
          class="flex gap-2"
        >
          <Avatar
            :src="m.employee?.avatar_path"
            :name="m.employee?.name"
            :size="24"
            class="shrink-0 mt-0.5"
          />
          <div class="min-w-0 flex-1">
            <p class="font-medium leading-snug text-slate-800 break-words">
              {{ m.employee?.name || '—' }}
            </p>
            <p
              v-if="m.branch?.label"
              class="mt-0.5 text-[10px] text-slate-500"
            >
              {{ m.branch.label }}
            </p>
          </div>
        </li>
      </ul>
    </div>

    <p
      v-else-if="leader && !roster.length"
      class="text-[10px] text-slate-500"
    >
      Chưa có thành viên khác
    </p>

    <div
      v-else-if="!leader && !roster.length"
      class="flex items-center gap-1.5 text-[10px] text-slate-500"
    >
      <AppIcon
        name="members"
        :size="14"
        class="shrink-0 text-slate-400"
      />
      <span>Chưa gán trưởng hoặc thành viên</span>
    </div>
  </div>
</template>
