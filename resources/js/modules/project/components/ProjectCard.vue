<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import { date } from '@/composables/useFormat';

const props = defineProps({
    project: { type: Object, required: true },
    draggable: { type: Boolean, default: false },
    showType: { type: Boolean, default: false },
    showDepartment: { type: Boolean, default: false },
});

const emit = defineEmits(['remove', 'dragstart']);

const stripe = {
    brand: 'bg-brand', sky: 'bg-sky-500', emerald: 'bg-emerald-500', violet: 'bg-violet-500',
    amber: 'bg-amber-500', rose: 'bg-rose-500', cyan: 'bg-cyan-500', slate: 'bg-slate-400',
};

const members = computed(() => {
    const list = props.project?.members;
    if (Array.isArray(list)) return list;
    if (list && typeof list === 'object') return Object.values(list).filter(Boolean);
    return [];
});
</script>

<template>
  <div
    class="card overflow-hidden transition hover:shadow-elevation-2"
    :class="draggable ? 'cursor-grab active:cursor-grabbing' : ''"
    :draggable="draggable"
    @dragstart="emit('dragstart', project)"
  >
    <div
      class="h-1.5"
      :class="stripe[project.color] || stripe.slate"
    />
    <div class="p-4">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <p class="font-mono text-xs text-slate-400">
            {{ project.code }}
          </p>
          <Link
            :href="`/projects/${project.id}`"
            class="block truncate font-display font-semibold text-slate-800 hover:text-brand"
          >
            {{ project.name }}
          </Link>
        </div>
        <Badge
          v-if="project.status"
          :label="project.status.label"
          :color="project.status.color"
        />
      </div>

      <div
        v-if="(showType && project.type) || (showDepartment && project.department)"
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
      </div>

      <div class="my-3">
        <ProgressBar :value="project.progress" />
      </div>

      <!-- Manager + due date row -->
      <div class="mb-3 flex items-center justify-between text-xs text-slate-500">
        <span
          v-if="project.manager"
          class="flex items-center gap-1.5"
        >
          <Avatar
            :name="project.manager.name"
            :src="project.manager.avatar_path"
            :size="20"
          />
          <span class="truncate max-w-[8rem]">{{ project.manager.name }}</span>
        </span>
        <span
          v-else
          class="text-slate-300"
        >Chưa có chủ dự án</span>
        <span
          v-if="project.due_date"
          class="flex items-center gap-1"
        >
          <AppIcon
            name="calendar"
            :size="12"
          />
          {{ date(project.due_date) }}
        </span>
      </div>

      <!-- Member avatars -->
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <template
            v-for="(m, i) in members.slice(0, 5)"
            :key="m.id"
          >
            <span
              :style="{ zIndex: 5 - i, marginLeft: i === 0 ? '0' : '-6px' }"
              class="relative inline-block rounded-full ring-2 ring-white"
            >
              <Avatar
                :name="m.name"
                :src="m.avatar_path"
                :size="24"
              />
            </span>
          </template>
          <span
            v-if="members.length > 5"
            class="relative ml-[-6px] inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-[10px] font-semibold text-slate-600 ring-2 ring-white"
          >
            +{{ members.length - 5 }}
          </span>
          <span
            v-if="!members.length"
            class="flex items-center gap-1 text-xs text-slate-400"
          >
            <AppIcon
              name="members"
              :size="13"
            /> 0 thành viên
          </span>
        </div>
        <div class="flex items-center gap-3 text-xs text-slate-500">
          <span class="flex items-center gap-1"><AppIcon
            name="task"
            :size="13"
          /> {{ project.task_count ?? 0 }}</span>
          <span
            v-if="project.open_blocker_count"
            class="flex items-center gap-1 text-rose-500"
          ><AppIcon
            name="blockers"
            :size="13"
          /> {{ project.open_blocker_count }}</span>
        </div>
      </div>

      <div class="mt-3 flex justify-end gap-1 border-t border-slate-100 pt-3">
        <Link
          v-if="project.can?.update"
          :href="`/projects/${project.id}/edit`"
          class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700"
          title="Sửa"
        >
          <AppIcon
            name="edit"
            :size="15"
          />
        </Link>
        <button
          v-if="project.can?.delete"
          class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600"
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
