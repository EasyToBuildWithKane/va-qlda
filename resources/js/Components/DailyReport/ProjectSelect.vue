<script setup>
import { computed, ref } from 'vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';

const props = defineProps({
    // [{ id, name, tasks: [{ id, title }] }]
    modelValue: { type: Array, default: () => [] },
    // [{ id, name, code, color, tasks: [{ id, title, status }] }]
    options: { type: Array, default: () => [] },
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
const statusColors = {
    todo: 'bg-slate-100 text-slate-500', in_progress: 'bg-sky-50 text-sky-700',
    in_review: 'bg-violet-50 text-violet-700', done: 'bg-emerald-50 text-emerald-700',
    blocked: 'bg-rose-50 text-rose-700',
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

const removeTask = (proj, taskId) => {
    const task = (proj.tasks || []).find((t) => t.id === taskId);
    confirmDelete(
        `Bỏ công việc "${task?.title ?? ''}" khỏi báo cáo?`,
        () => update(
            props.modelValue.map((p) =>
                p.id === proj.id ? { ...p, tasks: (p.tasks || []).filter((t) => t.id !== taskId) } : p,
            ),
        ),
        { title: 'Bỏ công việc', confirmText: 'Bỏ' },
    );
};

const statusOf = (proj, taskId) =>
    optionOf(proj.id)?.tasks?.find((t) => t.id === taskId)?.status ?? null;
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
            v-for="t in p.tasks"
            :key="t.id"
            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-700"
          >
            <span
              v-if="statusOf(p, t.id)"
              class="rounded px-1 py-0.5 text-[10px] font-medium"
              :class="statusColors[statusOf(p, t.id)] || 'bg-slate-100 text-slate-500'"
            >{{ statusLabels[statusOf(p, t.id)] || statusOf(p, t.id) }}</span>
            {{ t.title }}
            <button
              type="button"
              class="text-slate-400 hover:text-danger"
              :title="`Bỏ task ${t.title}`"
              @click="removeTask(p, t.id)"
            >×</button>
          </span>
        </div>

        <!-- Add task -->
        <select
          v-if="availableTasks(p).length"
          :value="taskPicker[p.id] || ''"
          class="input w-full cursor-pointer text-sm sm:w-auto sm:min-w-[16rem]"
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
      </div>
    </div>

    <!-- Add project -->
    <div class="flex items-center gap-2">
      <select
        v-if="available.length"
        v-model="picker"
        class="input w-full cursor-pointer text-sm sm:w-auto sm:min-w-[14rem]"
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
