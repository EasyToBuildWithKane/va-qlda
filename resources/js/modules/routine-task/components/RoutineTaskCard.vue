<script setup>
import { ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';

const props = defineProps({
    task: { type: Object, required: true },
    canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-status', 'update-title', 'delete']);

const confirmDelete = useConfirmDelete();
const editing = ref(false);
const draftTitle = ref(props.task.title ?? '');

watch(
    () => props.task.title,
    (v) => {
        if (!editing.value) draftTitle.value = v ?? '';
    },
);

const statusTone = {
    todo: 'bg-slate-100 text-slate-700 ring-slate-200',
    in_progress: 'bg-sky-50 text-sky-800 ring-sky-200',
    done: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
};

const saveTitle = () => {
    const next = draftTitle.value.trim();
    editing.value = false;
    if (!next || next === props.task.title) {
        draftTitle.value = props.task.title ?? '';
        return;
    }
    emit('update-title', next);
};

const onDelete = () => {
    confirmDelete(
        `Xoá việc «${props.task.title}»?`,
        () => emit('delete'),
        { title: 'Xoá việc thường xuyên', confirmText: 'Xoá' },
    );
};
</script>

<template>
  <article
    class="group flex items-start gap-3 rounded-xl border border-slate-200/80 bg-white px-3.5 py-3 shadow-sm transition hover:border-brand/30 hover:shadow"
    :class="{ 'opacity-70': task.status?.value === 'done' }"
  >
    <button
      v-if="canEdit"
      type="button"
      class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1 transition hover:bg-slate-50"
      :class="statusTone[task.status?.value] ?? statusTone.todo"
      :title="`Chuyển trạng thái (${task.status?.label ?? ''})`"
      :aria-label="`Chuyển trạng thái ${task.title}`"
      @click="emit('toggle-status')"
    >
      <AppIcon
        :name="task.status?.value === 'done' ? 'done' : (task.status?.value === 'in_progress' ? 'sprint' : 'task')"
        :size="15"
      />
    </button>
    <div
      v-else
      class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1"
      :class="statusTone[task.status?.value] ?? statusTone.todo"
    >
      <AppIcon
        :name="task.status?.value === 'done' ? 'done' : (task.status?.value === 'in_progress' ? 'sprint' : 'task')"
        :size="15"
      />
    </div>

    <div class="min-w-0 flex-1">
      <div
        v-if="editing && canEdit"
        class="flex items-center gap-2"
      >
        <input
          v-model="draftTitle"
          type="text"
          class="input h-9 w-full text-sm"
          maxlength="255"
          @keydown.enter.prevent="saveTitle"
          @keydown.esc.prevent="editing = false; draftTitle = task.title"
          @blur="saveTitle"
        >
      </div>
      <button
        v-else
        type="button"
        class="w-full text-left text-sm font-medium text-slate-800"
        :class="{ 'line-through text-slate-500': task.status?.value === 'done', 'cursor-text': canEdit }"
        :disabled="!canEdit"
        @click="canEdit && (editing = true)"
      >
        {{ task.title }}
      </button>
      <p
        v-if="task.description"
        class="mt-0.5 line-clamp-2 text-xs text-slate-500"
      >
        {{ task.description }}
      </p>
      <div class="mt-1.5 flex flex-wrap items-center gap-2">
        <span
          class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1"
          :class="statusTone[task.status?.value] ?? statusTone.todo"
        >
          {{ task.status?.label ?? 'Chưa cập nhật' }}
        </span>
      </div>
    </div>

    <button
      v-if="canEdit"
      type="button"
      class="mt-0.5 rounded-lg p-1.5 text-slate-400 opacity-0 transition hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100"
      :aria-label="`Xoá ${task.title}`"
      @click="onDelete"
    >
      <AppIcon
        name="trash"
        :size="14"
      />
    </button>
  </article>
</template>
