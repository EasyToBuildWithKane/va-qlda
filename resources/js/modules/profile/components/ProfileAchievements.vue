<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';
import ProfileProjectsCard from './ProfileProjectsCard.vue';
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

const statsBadge = computed(() => `${stats.value.projects_total ?? 0} dự án · ${stats.value.tasks_done ?? 0} việc`);
</script>

<template>
  <div class="space-y-5">
    <ProfileInfoPanel
      title="Thành tích làm việc"
      icon="leaderboard"
      subtitle="Dự án, việc đã xong và giờ làm đã ghi"
      section-key="profile-ach-stats"
      :collapsed-badge="statsBadge"
    >
      <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-3">
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
              Dự án đang làm
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
              Việc đã xong
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
              {{ hoursUp.display.value }}<span class="text-sm font-medium text-slate-400"> giờ</span>
            </p>
            <p class="profile-ach-tile__label">
              Giờ làm đã ghi nhận
            </p>
          </div>
        </div>
      </div>
    </ProfileInfoPanel>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
      <ProfileProjectsCard
        :projects="projects"
        section-key="profile-ach-projects"
      />

      <ProfileInfoPanel
        title="Dòng thời gian"
        icon="timeline"
        subtitle="Mốc gia nhập và tham gia dự án"
        section-key="profile-ach-timeline"
        :collapsed-badge="timeline.length ? `${timeline.length} mốc` : null"
      >
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
      </ProfileInfoPanel>
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
    background: rgb(248 250 252 / 0.5);
    padding: 1rem 1.1rem;
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
