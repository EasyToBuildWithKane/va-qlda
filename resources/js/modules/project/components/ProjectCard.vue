<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import ProjectMembers from '@/modules/project/components/ProjectMembers.vue';
import { date } from '@/composables/useFormat';
import { PROJECT_COLOR_SOFT, PROJECT_COLOR_SWATCH } from '@/modules/project/utils/projectColors';

const props = defineProps({
    project: { type: Object, required: true },
    draggable: { type: Boolean, default: false },
    showType: { type: Boolean, default: false },
    showDepartment: { type: Boolean, default: false },
});

const emit = defineEmits(['remove', 'dragstart']);

const stripe = PROJECT_COLOR_SWATCH;
const soft = PROJECT_COLOR_SOFT;

const members = computed(() => {
    const list = props.project?.members;
    if (Array.isArray(list)) return list;
    if (list && typeof list === 'object') return Object.values(list).filter(Boolean);
    return [];
});

const colorKey = computed(() => props.project.color || 'slate');
const stripeClass = computed(() => stripe[colorKey.value] || stripe.slate);
const softClass = computed(() => soft[colorKey.value] || soft.slate);

const progress = computed(() => {
    const n = Number(props.project.progress);
    return Number.isFinite(n) ? Math.max(0, Math.min(100, Math.round(n))) : 0;
});

const isOverdue = computed(() => {
    if (!props.project.due_date) return false;
    if (props.project.status?.value === 'completed') return false;
    const due = new Date(`${props.project.due_date}T23:59:59`);
    return Number.isFinite(due.getTime()) && due < new Date();
});

const hasMetaBadges = computed(
    () => (props.showType && props.project.type)
        || (props.showDepartment && (props.project.department || (props.project.related_departments || []).length)),
);
</script>

<template>
  <div
    class="project-card group relative text-left transition-[transform,box-shadow] duration-300 ease-out"
    :class="draggable ? 'cursor-grab active:cursor-grabbing' : ''"
    :draggable="draggable"
    @dragstart="emit('dragstart', project)"
  >
    <!-- Shell (kpi-card style, flat — không gradient) -->
    <div
      class="pointer-events-none absolute inset-0"
      aria-hidden="true"
    >
      <div class="project-card__shell absolute inset-0 bg-white" />
      <div
        class="project-card__accent absolute left-0 top-0 h-full w-1"
        :class="stripeClass"
      />
    </div>

    <div class="relative z-[1] flex min-h-0 flex-col p-3.5 pl-4">
      <!-- Header -->
      <div class="flex items-start justify-between gap-2.5">
        <div class="min-w-0">
          <span
            class="inline-flex max-w-full items-center rounded-md px-1.5 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wide ring-1 ring-inset ring-black/5"
            :class="softClass"
          >
            <span class="truncate">{{ project.code }}</span>
          </span>
          <Link
            :href="`/projects/${project.id}`"
            class="mt-1.5 block font-display text-[0.95rem] font-semibold leading-snug text-slate-800 transition group-hover:text-brand"
          >
            <span class="line-clamp-2">{{ project.name }}</span>
          </Link>
        </div>
        <Badge
          v-if="project.status"
          class="shrink-0"
          :label="project.status.label"
          :color="project.status.color"
        />
      </div>

      <div
        v-if="hasMetaBadges"
        class="mt-2 flex flex-wrap gap-1"
      >
        <Badge
          v-if="showType && project.type"
          :label="project.type.label"
          :color="project.type.color"
        />
        <Badge
          v-if="showDepartment && project.department"
          :label="project.department.name"
          :color="project.department.color"
        />
        <Badge
          v-for="d in (showDepartment ? (project.related_departments || []).slice(0, 2) : [])"
          :key="'rd-'+d.id"
          :label="d.name"
          :color="d.color"
        />
        <span
          v-if="showDepartment && (project.related_departments || []).length > 2"
          class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500"
        >+{{ project.related_departments.length - 2 }} liên đới</span>
      </div>

      <!-- Progress -->
      <div class="mt-3 rounded-lg border border-slate-200/80 bg-white px-2.5 py-2">
        <div class="mb-1.5 flex items-center justify-between gap-2">
          <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Tiến độ</span>
          <span class="font-display text-xs font-semibold tabular-nums text-slate-700">{{ progress }}%</span>
        </div>
        <ProgressBar
          :value="progress"
          :show-label="false"
          height="h-1.5"
        />
      </div>

      <!-- Manager + due -->
      <div class="mt-3 flex items-center justify-between gap-2 text-xs">
        <span
          v-if="project.manager"
          class="flex min-w-0 items-center gap-1.5 text-slate-600"
        >
          <Avatar
            :name="project.manager.name"
            :src="project.manager.avatar_path"
            :size="22"
            class="ring-1 ring-white"
          />
          <span class="truncate font-medium">{{ project.manager.name }}</span>
        </span>
        <span
          v-else
          class="text-slate-400"
        >Chưa có chủ dự án</span>

        <span
          v-if="project.due_date"
          class="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 font-medium tabular-nums"
          :class="isOverdue
            ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200/80'
            : 'bg-slate-50 text-slate-500 ring-1 ring-inset ring-slate-200/80'"
          :title="isOverdue ? 'Đã quá hạn' : 'Hạn dự án'"
        >
          <AppIcon
            name="calendar"
            :size="12"
          />
          {{ date(project.due_date) }}
        </span>
      </div>

      <!-- Members + stats -->
      <div class="mt-3 flex items-end justify-between gap-2 border-t border-slate-100 pt-3">
        <ProjectMembers
          :members="members"
          :max-visible="5"
          :max-name-labels="3"
          show-names
          compact
          class="min-w-0 flex-1"
        />
        <div class="flex shrink-0 items-center gap-1.5 pb-0.5">
          <span
            class="inline-flex items-center gap-1 rounded-md bg-slate-50 px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-slate-600 ring-1 ring-inset ring-slate-200/80"
            title="Công việc"
          >
            <AppIcon
              name="task"
              :size="12"
              class="text-slate-400"
            />
            {{ project.task_count ?? 0 }}
          </span>
          <span
            v-if="project.open_blocker_count"
            class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-rose-700 ring-1 ring-inset ring-rose-200/80"
            title="vướng mắc đang mở"
          >
            <AppIcon
              name="blockers"
              :size="12"
            />
            {{ project.open_blocker_count }}
          </span>
        </div>
      </div>

      <!-- Actions -->
      <div
        v-if="project.can?.update || project.can?.delete"
        class="mt-2.5 flex justify-end gap-0.5"
      >
        <Link
          v-if="project.can?.update"
          :href="`/projects/${project.id}/edit`"
          class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
          title="Sửa"
        >
          <AppIcon
            name="edit"
            :size="15"
          />
        </Link>
        <button
          v-if="project.can?.delete"
          type="button"
          class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
          title="Xoá"
          @click="emit('remove', project)"
        >
          <AppIcon
            name="delete"
            :size="15"
          />
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.project-card__shell {
    clip-path: polygon(0 0, 100% 0, 100% calc(100% - 14px), calc(100% - 14px) 100%, 0 100%);
    box-shadow:
        0 1px 2px rgb(15 23 42 / 0.04),
        inset 0 0 0 1px rgb(226 232 240 / 0.95);
    outline: 1px dashed rgb(203 213 225 / 0.75);
    outline-offset: -4px;
    transition:
        box-shadow 0.3s ease,
        outline-color 0.3s ease,
        outline-style 0.3s ease;
}

.project-card__accent {
    clip-path: polygon(0 0, 100% 0, 100% calc(100% - 14px), 0 100%);
}

.project-card:hover {
    transform: translateY(-3px);
}

.project-card:hover .project-card__shell {
    box-shadow:
        0 8px 24px rgb(15 23 42 / 0.08),
        inset 0 0 0 1px rgb(154 0 54 / 0.28);
    outline-color: rgb(154 0 54 / 0.35);
    outline-style: solid;
}

.project-card:focus-within {
    transform: translateY(-2px);
}

.project-card:focus-within .project-card__shell {
    box-shadow:
        0 0 0 3px rgb(154 0 54 / 0.18),
        0 8px 24px rgb(15 23 42 / 0.06),
        inset 0 0 0 1px rgb(154 0 54 / 0.28);
    outline-color: rgb(154 0 54 / 0.4);
    outline-style: solid;
}

@media (prefers-reduced-motion: reduce) {
    .project-card:hover,
    .project-card:focus-within {
        transform: none;
    }
}
</style>
