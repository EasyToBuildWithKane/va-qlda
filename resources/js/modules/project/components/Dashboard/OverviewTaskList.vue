<script setup>
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import { date } from '@/composables/useFormat';
import { taskDeadlineClass } from '@/composables/useProjectDashboard';

defineProps({
    tasks: { type: Array, default: () => [] },
    title: { type: String, default: 'Công việc' },
});

const emit = defineEmits(['select']);
</script>

<template>
  <div class="card overflow-hidden p-5 dark:border-slate-700 dark:bg-slate-900">
    <h2 class="mb-3 font-display font-semibold text-slate-800 dark:text-slate-100">
      {{ title }}
    </h2>
    <div
      v-if="tasks.length"
      class="divide-y divide-slate-100 dark:divide-slate-700"
    >
      <button
        v-for="t in tasks"
        :key="t.id"
        type="button"
        class="flex w-full items-center gap-3 py-2.5 pl-2 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800/50"
        :class="taskDeadlineClass(t)"
        @click="emit('select', t)"
      >
        <div class="min-w-0 flex-1">
          <p class="truncate font-medium text-slate-800 dark:text-slate-100">
            {{ t.title }}
          </p>
          <p class="mt-0.5 text-xs text-slate-400">
            Hạn: {{ date(t.due_date) }}
          </p>
        </div>
        <Badge
          :label="t.status?.label"
          :color="t.status?.color"
        />
        <div class="hidden w-24 sm:block">
          <ProgressBar :value="t.progress" />
        </div>
        <div
          v-if="t.assignee || t.assignees?.length"
          class="hidden shrink-0 sm:flex"
        >
          <Avatar
            v-if="t.assignees?.length"
            :name="t.assignees[0].name"
            :src="t.assignees[0].avatar_path"
            :size="28"
          />
          <Avatar
            v-else-if="t.assignee"
            :name="t.assignee.name"
            :src="t.assignee.avatar_path"
            :size="28"
          />
        </div>
      </button>
    </div>
    <p
      v-else
      class="py-6 text-center text-sm text-slate-400"
    >
      Không có công việc.
    </p>
  </div>
</template>
