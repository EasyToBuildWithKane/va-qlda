<script setup>
import { computed, ref } from 'vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import TaskStatusBadge from '@/Components/TaskStatusBadge.vue';
import { createSpawnLocalKey } from '@/modules/daily-report/utils/spawnLocalKey';

const props = defineProps({
    // [{ id, name, tasks: [{ id, title }] }]
    modelValue: { type: Array, default: () => [] },
    // [{ id, name, code, color, tasks: [{ id, title, status }] }]
    options: { type: Array, default: () => [] },
    taskStatusSnapshot: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);
const confirmDelete = useConfirmDelete();

// Soft tag colours (literal strings so Tailwind keeps them).
const soft = {
    brand: 'bg-brand-50 text-brand', sky: 'bg-sky-50 text-sky-700',
    emerald: 'bg-emerald-50 text-emerald-700', violet: 'bg-violet-50 text-violet-700',
    amber: 'bg-amber-50 text-amber-700', rose: 'bg-rose-50 text-rose-700',
    cyan: 'bg-cyan-50 text-cyan-700', slate: 'bg-slate-100 text-slate-600',
};
const optionOf = (id) => props.options.find((o) => o.id === id);
const colorOf = (id) => soft[optionOf(id)?.color] || soft.slate;

const statusLabels = {
    todo: 'Cần làm', in_progress: 'Đang làm', in_review: 'Đang review',
    done: 'Hoàn thành', blocked: 'Bị chặn',
};

const selectedIds = computed(() => new Set(props.modelValue.map((p) => p.id)));
const available = computed(() => props.options.filter((o) => !selectedIds.value.has(o.id)));

const update = (next) => emit('update:modelValue', next);

// ---- Projects -------------------------------------------------------------
const picker = ref('');
const addProject = () => {
    const opt = optionOf(Number(picker.value));
    if (opt) update([...props.modelValue, { id: opt.id, name: opt.name, tasks: [] }]);
    picker.value = '';
};
const removeProject = (id) => {
    const proj = props.modelValue.find((p) => p.id === id);
    const taskCount = proj?.tasks?.length ?? 0;
    confirmDelete(
        taskCount
            ? `Bỏ dự án "${proj?.name}" và ${taskCount} công việc đã chọn?`
            : `Bỏ dự án "${proj?.name}" khỏi báo cáo?`,
        () => update(props.modelValue.filter((p) => p.id !== id)),
        { title: 'Bỏ dự án', confirmText: 'Bỏ' },
    );
};

// ---- Tasks per project ----------------------------------------------------
const taskPicker = ref({}); // { [projectId]: '' } — resets each select

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
    update(props.modelValue.map((p) =>
        p.id === proj.id
            ? { ...p, tasks: [...(p.tasks || []), { id: task.id, title: task.title }] }
            : p,
    ));
};

const removeTask = (proj, taskIndex) => {
    const task = (proj.tasks || [])[taskIndex];
    confirmDelete(
        `Bỏ công việc "${task?.title ?? ''}" khỏi báo cáo?`,
        () => update(
            props.modelValue.map((p) =>
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
    update(props.modelValue.map((p) =>
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
</script>

<template>
  <div class="space-y-3">
    <!-- Selected project cards -->
    <div
      v-for="p in modelValue"
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
        <!-- Chosen tasks -->
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

        <!-- Add task -->
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

    <!-- Add project -->
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
        v-else
        class="text-xs text-slate-400"
      >Đã chọn tất cả dự án.</span>
    </div>

    <p
      v-if="!modelValue.length"
      class="text-xs text-slate-400"
    >
      Chọn các dự án bạn đã làm việc hôm nay (có thể chọn nhiều). Sau khi chọn dự án, bạn có thể thêm các task tương ứng.
    </p>
  </div>
</template>
