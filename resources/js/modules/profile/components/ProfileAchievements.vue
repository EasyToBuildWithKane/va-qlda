<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import { useCountUp } from '@/shared/composables/useCountUp.js';
import { date } from '@/composables/useFormat';

const props = defineProps({
    profile: { type: Object, required: true },
});

const stats = computed(() => props.profile.stats ?? {});
const projects = computed(() => props.profile.current_projects ?? []);

const projectsUp = useCountUp(() => stats.value.projects_total ?? 0);
const tasksUp = useCountUp(() => stats.value.tasks_done ?? 0);
const hoursUp = useCountUp(() => Math.round(stats.value.worklog_hours ?? 0));

const roleLabel = {
    manager: 'Quản lý',
    lead: 'Trưởng nhóm',
    member: 'Thành viên',
    reviewer: 'Reviewer',
    viewer: 'Theo dõi',
};

const timeline = computed(() => {
    const events = [];
    if (props.profile.join_date) {
        events.push({
            key: 'join',
            date: props.profile.join_date,
            title: 'Gia nhập tổ chức',
            icon: 'rocket',
        });
    }
    for (const p of projects.value) {
        if (p.joined_at) {
            events.push({
                key: `proj-${p.id}`,
                date: p.joined_at,
                title: `Tham gia dự án ${p.name}`,
                icon: 'projects',
            });
        }
    }
    return events.sort((a, b) => new Date(b.date) - new Date(a.date));
});
</script>

<template>
  <div class="space-y-5">
    <!-- Stat tiles -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div class="profile-ach-tile">
        <div class="profile-ach-tile__icon bg-brand/10 text-brand">
          <AppIcon
            name="projects"
            :size="18"
          />
        </div>
        <div>
          <p class="profile-ach-tile__value">
            {{ projectsUp.display.value }}
          </p>
          <p class="profile-ach-tile__label">
            Dự án tham gia
          </p>
        </div>
      </div>

      <div class="profile-ach-tile">
        <div class="profile-ach-tile__icon bg-emerald-50 text-emerald-600">
          <AppIcon
            name="done"
            :size="18"
          />
        </div>
        <div>
          <p class="profile-ach-tile__value">
            {{ tasksUp.display.value }}
            <span
              v-if="stats.tasks_total"
              class="text-sm font-medium text-slate-400"
            >/ {{ stats.tasks_total }}</span>
          </p>
          <p class="profile-ach-tile__label">
            Công việc hoàn thành
            <span v-if="stats.task_completion != null">· {{ stats.task_completion }}%</span>
          </p>
        </div>
      </div>

      <div class="profile-ach-tile">
        <div class="profile-ach-tile__icon bg-sky-50 text-sky-600">
          <AppIcon
            name="worklog"
            :size="18"
          />
        </div>
        <div>
          <p class="profile-ach-tile__value">
            {{ hoursUp.display.value }}<span class="text-sm font-medium text-slate-400">h</span>
          </p>
          <p class="profile-ach-tile__label">
            Giờ ghi nhận (worklog)
          </p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
      <!-- Projects -->
      <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
        <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
          <AppIcon
            name="projects"
            :size="16"
            class="text-slate-400"
          />
          <h2 class="text-sm font-semibold text-slate-800">
            Dự án đang tham gia
          </h2>
        </header>
        <div class="p-5">
          <ul
            v-if="projects.length"
            class="space-y-2.5"
          >
            <li
              v-for="p in projects"
              :key="p.id"
              class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5"
            >
              <span
                class="h-8 w-1.5 shrink-0 rounded-full"
                :style="{ backgroundColor: p.color || '#9a0036' }"
              />
              <div class="min-w-0 flex-1">
                <p class="truncate text-[13px] font-medium text-slate-800">
                  {{ p.name }}
                </p>
                <p class="truncate text-[11.5px] text-slate-400">
                  {{ p.code }}
                </p>
              </div>
              <span
                v-if="p.role"
                class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"
              >
                {{ roleLabel[p.role] || p.role }}
              </span>
              <span
                v-if="p.allocation"
                class="shrink-0 text-[11px] font-medium text-slate-400"
              >{{ p.allocation }}%</span>
            </li>
          </ul>
          <EmptyState
            v-else
            icon="projects"
            title="Chưa tham gia dự án"
            description="Khi được phân vào dự án, danh sách sẽ hiển thị tại đây."
          />
        </div>
      </section>

      <!-- Timeline -->
      <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
        <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
          <AppIcon
            name="timeline"
            :size="16"
            class="text-slate-400"
          />
          <h2 class="text-sm font-semibold text-slate-800">
            Dòng thời gian
          </h2>
        </header>
        <div class="p-5">
          <ol
            v-if="timeline.length"
            class="relative space-y-5 border-l border-slate-200 pl-5"
          >
            <li
              v-for="ev in timeline"
              :key="ev.key"
              class="relative"
            >
              <span class="absolute -left-[27px] grid h-6 w-6 place-items-center rounded-full bg-brand/10 text-brand ring-4 ring-white">
                <AppIcon
                  :name="ev.icon"
                  :size="12"
                />
              </span>
              <p class="text-[13px] font-medium text-slate-700">
                {{ ev.title }}
              </p>
              <p class="text-[11.5px] text-slate-400">
                {{ date(ev.date) }}
              </p>
            </li>
          </ol>
          <EmptyState
            v-else
            icon="timeline"
            title="Chưa có mốc thời gian"
            description="Ngày tham gia và dự án sẽ tạo nên dòng thời gian của bạn."
          />
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.profile-ach-tile {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    border-radius: 16px;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: #fff;
    padding: 1rem 1.1rem;
    box-shadow: 0 10px 26px -22px rgba(15, 23, 42, 0.5);
}

.profile-ach-tile__icon {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 12px;
    flex-shrink: 0;
}

.profile-ach-tile__value {
    font-family: var(--font-display, inherit);
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.profile-ach-tile__label {
    margin-top: 0.3rem;
    font-size: 11.5px;
    color: #64748b;
}
</style>
