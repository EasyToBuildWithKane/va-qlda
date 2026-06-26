<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';

const props = defineProps({
    data: { type: Object, default: null },
});

const summary = computed(() => props.data?.summary ?? {});
const tasks = computed(() => props.data?.tasks ?? []);

const stats = computed(() => [
    { label: 'Đang mở', value: summary.value.open ?? 0, tone: 'text-slate-700' },
    { label: 'Quá hạn', value: summary.value.overdue ?? 0, tone: 'text-rose-600' },
    { label: 'Hôm nay', value: summary.value.dueToday ?? 0, tone: 'text-amber-600' },
    { label: 'Giờ log', value: summary.value.hoursLoggedToday ?? 0, tone: 'text-emerald-600' },
]);

function openTask(task) {
    router.visit(`/projects/${task.project_id}?task=${task.id}`);
}
</script>

<template>
  <section class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <header class="mb-3 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
          <AppIcon
            name="calendar-clock"
            :size="16"
          />
        </span>
        <div>
          <h2 class="font-display text-sm font-semibold text-slate-800 dark:text-slate-100">
            Việc hôm nay của tôi
          </h2>
          <p class="text-[11px] text-slate-400">
            Ưu tiên việc quá hạn &amp; đến hạn
          </p>
        </div>
      </div>
      <Link
        href="/my-work"
        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-brand hover:bg-brand/5"
      >
        Xem tất cả
        <AppIcon
          name="chevron-right"
          :size="14"
        />
      </Link>
    </header>

    <!-- mini stats -->
    <div class="mb-3 grid grid-cols-4 gap-2">
      <div
        v-for="s in stats"
        :key="s.label"
        class="rounded-lg bg-slate-50 px-2 py-1.5 text-center dark:bg-slate-800/60"
      >
        <p
          class="font-display text-lg font-bold tabular-nums"
          :class="s.tone"
        >
          {{ s.value }}
        </p>
        <p class="text-[10px] uppercase tracking-wide text-slate-400">
          {{ s.label }}
        </p>
      </div>
    </div>

    <!-- top tasks -->
    <ul
      v-if="tasks.length"
      class="space-y-1.5"
    >
      <li
        v-for="task in tasks"
        :key="task.id"
      >
        <button
          type="button"
          class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800"
          @click="openTask(task)"
        >
          <span
            class="h-2 w-2 shrink-0 rounded-full"
            :style="{ backgroundColor: task.project?.color || '#94a3b8' }"
          />
          <span class="min-w-0 flex-1 truncate text-xs text-slate-700 dark:text-slate-200">{{ task.title }}</span>
          <Badge
            v-if="task.status"
            :label="task.status.label"
            :color="task.status.color"
          />
        </button>
      </li>
    </ul>
    <p
      v-else
      class="rounded-lg bg-slate-50 px-3 py-4 text-center text-xs text-slate-400 dark:bg-slate-800/50"
    >
      Không có việc quá hạn hay đến hạn hôm nay 🎉
    </p>
  </section>
</template>
