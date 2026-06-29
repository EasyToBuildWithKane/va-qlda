<script setup>
import { computed, ref } from 'vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import TaskStatusBadge from '@/Components/TaskStatusBadge.vue';
import { createSpawnLocalKey } from '@/modules/daily-report/utils/spawnLocalKey';
import {
    ROUTINE_PROJECT_ID,
    ROUTINE_PROJECT_NAME,
    isRoutineProjectEntry,
} from '@/modules/daily-report/constants/routineWork';
import { PROJECT_COLOR_SOFT } from '@/modules/project/utils/projectColors';

const props = defineProps({
    // [{ id, name, tasks: [{ id, title }] }]
    modelValue: { type: Array, default: () => [] },
    // [{ id, name, code, color, tasks: [{ id, title, status }] }]
    options: { type: Array, default: () => [] },
    taskStatusSnapshot: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);
const confirmDelete = useConfirmDelete();

const soft = PROJECT_COLOR_SOFT;
const optionOf = (id) => props.options.find((o) => o.id === id);
const colorOf = (id) => soft[optionOf(id)?.color] || soft.slate;

const statusLabels = {
    todo: 'Cần làm', in_progress: 'Đang làm', in_review: 'Đang review',
    done: 'Hoàn thành', blocked: 'Bị chặn',
};

const realProjects = computed(() => props.modelValue.filter((p) => !isRoutineProjectEntry(p)));
const routineEntry = computed(() => props.modelValue.find(isRoutineProjectEntry) ?? null);
const routineTasks = computed(() => routineEntry.value?.tasks ?? []);

const selectedIds = computed(() => new Set(realProjects.value.map((p) => p.id)));
const available = computed(() => props.options.filter((o) => !selectedIds.value.has(o.id)));

const update = (next) => emit('update:modelValue', next);

const mergeWithRoutine = (projects, tasks) => {
    const list = [...projects];
    if (tasks.length > 0) {
        list.push({ id: ROUTINE_PROJECT_ID, name: ROUTINE_PROJECT_NAME, tasks });
    }
    return list;
};

const updateRealProjects = (projects) => {
    update(mergeWithRoutine(projects, routineTasks.value));
};

const setRoutineTasks = (tasks) => {
    update(mergeWithRoutine(realProjects.value, tasks));
};

// ---- Projects -------------------------------------------------------------
const picker = ref('');
const addProject = () => {
    const opt = optionOf(Number(picker.value));
    if (opt) {
        updateRealProjects([...realProjects.value, { id: opt.id, name: opt.name, tasks: [] }]);
    }
    picker.value = '';
};
const removeProject = (id) => {
    const proj = realProjects.value.find((p) => p.id === id);
    const taskCount = proj?.tasks?.length ?? 0;
    confirmDelete(
        taskCount
            ? `Bỏ dự án "${proj?.name}" và ${taskCount} công việc đã chọn?`
            : `Bỏ dự án "${proj?.name}" khỏi báo cáo?`,
        () => updateRealProjects(realProjects.value.filter((p) => p.id !== id)),
        { title: 'Bỏ dự án', confirmText: 'Bỏ' },
    );
};

// ---- Tasks per project ----------------------------------------------------
const taskPicker = ref({});

const availableTasks = (proj) => {
    const opt = optionOf(proj.id);
    if (!opt?.tasks?.length) return [];
    const taken = new Set((proj.tasks || []).map((t) => t.id));
    return opt.tasks.filter((t) => !taken.has(t.id));
};

const addTask = (proj, event) => {
    const id = Number(event.target.value);
    const task = optionOf(proj.id)?.tasks?.find((t) => t.id === id);
    taskPicker.value[proj.id] = '';
    if (!task) return;
    updateRealProjects(realProjects.value.map((p) =>
        p.id === proj.id
            ? { ...p, tasks: [...(p.tasks || []), { id: task.id, title: task.title }] }
            : p,
    ));
};

const removeTask = (proj, taskIndex) => {
    const task = (proj.tasks || [])[taskIndex];
    confirmDelete(
        `Bỏ công việc "${task?.title ?? ''}" khỏi báo cáo?`,
        () => updateRealProjects(
            realProjects.value.map((p) =>
                p.id === proj.id
                    ? { ...p, tasks: (p.tasks || []).filter((_, i) => i !== taskIndex) }
                    : p,
            ),
        ),
        { title: 'Bỏ công việc', confirmText: 'Bỏ' },
    );
};

const statusOf = (proj, taskId) =>
    proj.tasks?.find((t) => t.id === taskId)?.status
    ?? optionOf(proj.id)?.tasks?.find((t) => t.id === taskId)?.status
    ?? 'todo';

const spawnDraft = ref({});

const addSpawnedTask = (proj) => {
    const title = (spawnDraft.value[proj.id] || '').trim();
    if (!title) return;
    updateRealProjects(realProjects.value.map((p) =>
        p.id === proj.id
            ? {
                ...p,
                tasks: [...(p.tasks || []), {
                    id: 0,
                    title,
                    status: 'todo',
                    _localKey: createSpawnLocalKey(),
                }],
            }
            : p,
    ));
    spawnDraft.value[proj.id] = '';
};

// ---- Routine (not tied to any project) ------------------------------------
const routineDraft = ref('');

const addRoutineTask = () => {
    const title = routineDraft.value.trim();
    if (!title) return;
    setRoutineTasks([
        ...routineTasks.value,
        { id: 0, title, status: 'todo' },
    ]);
    routineDraft.value = '';
};

const removeRoutineTask = (taskIndex) => {
    const task = routineTasks.value[taskIndex];
    confirmDelete(
        `Bỏ "${task?.title ?? ''}" khỏi việc thường xuyên?`,
        () => setRoutineTasks(routineTasks.value.filter((_, i) => i !== taskIndex)),
        { title: 'Bỏ công việc', confirmText: 'Bỏ' },
    );
};
</script>

<template>
  <div class="space-y-6">
    <!-- Dự án -->
    <section
      class="space-y-3"
      aria-labelledby="daily-report-projects-heading"
    >
      <div>
        <h3
          id="daily-report-projects-heading"
          class="text-sm font-semibold text-slate-800"
        >
          Dự án
        </h3>
        <p class="mt-0.5 text-xs text-slate-500">
          Chọn dự án và task bạn đã làm trong ngày.
        </p>
      </div>

      <div
        v-for="p in realProjects"
        :key="p.id"
        class="rounded-card border border-slate-200 bg-white"
      >
        <div class="flex items-center justify-between gap-2 px-3 py-2">
          <span
            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold"
            :class="colorOf(p.id)"
          >{{ p.name }}</span>
          <button
            type="button"
            class="grid h-6 w-6 place-items-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600"
            :title="`Bỏ ${p.name}`"
            @click="removeProject(p.id)"
          >
            ×
          </button>
        </div>

        <div class="border-t border-slate-100 px-3 py-2.5">
          <div
            v-if="(p.tasks || []).length"
            class="mb-2 flex flex-wrap gap-1.5"
          >
            <span
              v-for="(t, idx) in p.tasks"
              :key="`${p.id}-${t.id}-${idx}`"
              class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700"
            >
              <TaskStatusBadge
                v-if="t.id > 0"
                :task-id="t.id"
                :initial-status="statusOf(p, t.id)"
                :snapshot="taskStatusSnapshot"
              />
              <span
                v-else
                class="rounded px-1 py-0.5 text-[10px] font-medium bg-amber-50 text-amber-700"
              >Phát sinh</span>
              {{ t.title }}
              <button
                type="button"
                class="text-slate-400 hover:text-danger"
                :title="`Bỏ task ${t.title}`"
                @click="removeTask(p, idx)"
              >×</button>
            </span>
          </div>

          <select
            v-if="availableTasks(p).length"
            :value="taskPicker[p.id] || ''"
            class="input w-full cursor-pointer text-sm"
            @change="addTask(p, $event)"
          >
            <option
              value=""
              disabled
            >
              + Thêm task của dự án này…
            </option>
            <option
              v-for="t in availableTasks(p)"
              :key="t.id"
              :value="t.id"
            >
              {{ t.title }}{{ t.status ? ` — ${statusLabels[t.status] || t.status}` : '' }}
            </option>
          </select>
          <p
            v-else-if="!(optionOf(p.id)?.tasks?.length)"
            class="text-xs text-slate-400"
          >
            Dự án này chưa có task nào.
          </p>
          <p
            v-else
            class="text-xs text-slate-400"
          >
            Đã thêm tất cả task của dự án.
          </p>

          <div class="mt-2 flex flex-wrap items-center gap-2">
            <input
              v-model="spawnDraft[p.id]"
              type="text"
              class="input min-w-0 flex-1 text-sm"
              placeholder="Task phát sinh trong ngày…"
              maxlength="255"
              @keydown.enter.prevent="addSpawnedTask(p)"
            >
            <button
              type="button"
              class="btn-ghost shrink-0 text-xs"
              @click="addSpawnedTask(p)"
            >
              + Phát sinh
            </button>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <select
          v-if="available.length"
          v-model="picker"
          class="input w-full cursor-pointer text-sm"
          @change="addProject"
        >
          <option
            value=""
            disabled
          >
            + Thêm dự án…
          </option>
          <option
            v-for="o in available"
            :key="o.id"
            :value="o.id"
          >
            {{ o.name }}
          </option>
        </select>
        <span
          v-else-if="!realProjects.length"
          class="text-xs text-slate-400"
        >
          Chưa chọn dự án nào.
        </span>
        <span
          v-else
          class="text-xs text-slate-400"
        >
          Đã chọn tất cả dự án của bạn.
        </span>
      </div>
    </section>

    <!-- Việc thường xuyên — không gắn dự án -->
    <section
      class="space-y-3 border-t border-slate-200 pt-5"
      aria-labelledby="daily-report-routine-heading"
    >
      <div>
        <h3
          id="daily-report-routine-heading"
          class="text-sm font-semibold text-amber-800"
        >
          Công việc thường xuyên
        </h3>
        <p class="mt-0.5 text-xs text-slate-500">
          Việc lặp lại hằng ngày (họp, email, vận hành…) — <strong class="font-medium text-slate-600">không</strong> gắn với dự án cụ thể, vẫn được ghi nhận trong báo cáo.
        </p>
      </div>

      <div class="rounded-card border border-amber-200 bg-amber-50/30 px-3 py-2.5">
        <div
          v-if="routineTasks.length"
          class="mb-2 flex flex-wrap gap-1.5"
        >
          <span
            v-for="(t, idx) in routineTasks"
            :key="`routine-${idx}-${t.title}`"
            class="inline-flex items-center gap-1.5 rounded-md border border-amber-200/80 bg-white px-2 py-1 text-xs text-slate-700"
          >
            {{ t.title }}
            <button
              type="button"
              class="text-slate-400 hover:text-danger"
              :title="`Bỏ ${t.title}`"
              @click="removeRoutineTask(idx)"
            >×</button>
          </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <input
            v-model="routineDraft"
            type="text"
            class="input min-w-0 flex-1 text-sm"
            placeholder="VD: Họp daily, trả lời email nội bộ…"
            maxlength="255"
            @keydown.enter.prevent="addRoutineTask"
          >
          <button
            type="button"
            class="btn-ghost shrink-0 border border-amber-200 text-xs text-amber-800 hover:bg-amber-50"
            @click="addRoutineTask"
          >
            + Thêm
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
