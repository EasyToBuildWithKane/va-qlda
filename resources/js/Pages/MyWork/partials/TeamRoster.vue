<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';

defineProps({
    members: { type: Array, default: () => [] },
});

const emit = defineEmits(['select']);
</script>

<template>
  <div>
    <div
      v-if="members.length === 0"
      class="rounded-xl border border-dashed border-slate-200 bg-white px-5 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900"
    >
      <AppIcon
        name="people"
        :size="28"
        class="mx-auto mb-2 text-slate-300"
      />
      Bạn chưa phụ trách nhóm nào, hoặc nhóm chưa có thành viên.
    </div>

    <div
      v-else
      class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
    >
      <button
        v-for="m in members"
        :key="m.id"
        type="button"
        class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-brand/40 hover:shadow-sm dark:border-slate-700 dark:bg-slate-900"
        @click="emit('select', m.id)"
      >
        <Avatar
          :name="m.name"
          :src="m.avatar_path"
          :size="40"
        />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
            {{ m.name }}
          </p>
          <p
            v-if="m.role_title"
            class="truncate text-[11px] text-slate-400"
          >
            {{ m.role_title }}
          </p>
          <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
              {{ m.open }} việc
            </span>
            <span
              v-if="m.overdue > 0"
              class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-1.5 py-0.5 font-medium text-rose-700"
            >
              {{ m.overdue }} quá hạn
            </span>
            <span
              v-if="m.dueToday > 0"
              class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 font-medium text-amber-700"
            >
              {{ m.dueToday }} hôm nay
            </span>
          </div>
        </div>
        <AppIcon
          name="chevron-right"
          :size="16"
          class="shrink-0 text-slate-300"
        />
      </button>
    </div>
  </div>
</template>
