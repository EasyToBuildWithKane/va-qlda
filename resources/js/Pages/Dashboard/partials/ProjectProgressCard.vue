<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import ProgressRing from './ProgressRing.vue';

const props = defineProps({
    project: { type: Object, required: true },
});

const ringColor = computed(() => props.project.color || '#9A0036');

const due = computed(() => {
    const d = props.project.daysLeft;
    if (d === null || d === undefined) {
        return { text: 'Chưa đặt hạn', tone: 'text-slate-400' };
    }
    if (d < 0) return { text: `Quá hạn ${Math.abs(d)} ngày`, tone: 'text-rose-600' };
    if (d === 0) return { text: 'Đến hạn hôm nay', tone: 'text-amber-600' };
    if (d <= 7) return { text: `Còn ${d} ngày`, tone: 'text-amber-600' };
    return { text: `Còn ${d} ngày`, tone: 'text-slate-500' };
});

const extraMembers = computed(() =>
    Math.max(0, (props.project.membersTotal ?? 0) - (props.project.members?.length ?? 0)),
);
</script>

<template>
  <Link
    :href="route('projects.show', project.id)"
    class="card group flex flex-col gap-3 p-4 transition hover:border-brand/40 hover:shadow-md"
  >
    <div class="flex items-start gap-3">
      <ProgressRing
        :value="project.progress"
        :size="56"
        :stroke="6"
        :color="ringColor"
      />
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <span
            class="h-2.5 w-2.5 shrink-0 rounded-full"
            :style="{ backgroundColor: ringColor }"
          />
          <p class="truncate font-display font-semibold text-slate-800 group-hover:text-brand">
            {{ project.name }}
          </p>
        </div>
        <p class="mt-0.5 text-xs text-slate-400">
          {{ project.code }}
        </p>
        <div class="mt-1.5">
          <Badge
            :label="project.statusLabel"
            :color="project.statusColor"
          />
        </div>
      </div>
    </div>

    <div class="flex items-center justify-between text-xs text-slate-500">
      <span class="inline-flex items-center gap-1">
        <AppIcon
          name="check-circle"
          :size="13"
          class="text-emerald-500"
        />
        {{ project.tasksDone }}/{{ project.tasksTotal }} việc
      </span>
      <span
        class="inline-flex items-center gap-1"
        :class="due.tone"
      >
        <AppIcon
          name="calendar"
          :size="13"
        />
        {{ due.text }}
      </span>
    </div>

    <div class="flex items-center justify-between border-t border-slate-100 pt-3">
      <div class="flex -space-x-2">
        <Avatar
          v-for="m in project.members"
          :key="m.name"
          :name="m.name"
          :src="m.avatar"
          :size="26"
          class="ring-2 ring-white"
        />
        <span
          v-if="extraMembers > 0"
          class="inline-grid h-[26px] w-[26px] place-items-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-500 ring-2 ring-white"
        >+{{ extraMembers }}</span>
        <span
          v-if="!project.members?.length"
          class="text-[11px] text-slate-400"
        >Chưa có thành viên</span>
      </div>
      <span
        v-if="project.openBlockers > 0"
        class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-600"
      >
        <AppIcon
          name="blockers"
          :size="12"
        />
        {{ project.openBlockers }}
      </span>
    </div>
  </Link>
</template>
