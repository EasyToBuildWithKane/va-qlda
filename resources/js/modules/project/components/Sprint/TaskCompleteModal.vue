<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTaskCompleteModal } from '@/composables/useTaskCompleteModal';
import { getTaskSlaToneClass } from '@/composables/useTaskTimeliness';

const props = defineProps({
    projectId: { type: Number, required: true },
});

const {
    open,
    targetTask,
    actualHours,
    completionNote,
    submitting,
    estimateHours,
    previewTiming,
    previewSla,
    dirty,
    close,
    submit,
} = useTaskCompleteModal();

const onSubmit = () => submit(props.projectId);
</script>

<template>
  <Modal
    :show="open"
    title="Xác nhận hoàn thành"
    max-width="max-w-md"
    :dirty="dirty"
    close-confirm-title="Huỷ hoàn thành?"
    close-confirm-message="Thông tin giờ thực tế chưa được lưu."
    @close="close"
  >
    <p
      v-if="targetTask"
      class="mb-4 text-sm text-slate-600 dark:text-slate-300"
    >
      <span class="font-mono text-xs text-slate-400">#{{ targetTask.id }}</span>
      {{ targetTask.title }}
    </p>

    <div class="space-y-4">
      <div
        v-if="estimateHours != null"
        class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:bg-slate-800/80 dark:text-slate-300"
      >
        Giờ ước tính (kế hoạch): <strong class="tabular-nums">{{ estimateHours }}h</strong>
      </div>

      <div>
        <label
          for="task-complete-hours"
          class="mb-1 block text-xs font-medium text-slate-500"
        >Giờ thực tế <span class="text-rose-600">*</span></label>
        <input
          id="task-complete-hours"
          v-model="actualHours"
          type="number"
          min="0.01"
          step="0.25"
          inputmode="decimal"
          class="input w-full tabular-nums"
          placeholder="VD: 4.5"
          autofocus
        >
      </div>

      <div>
        <label
          for="task-complete-note"
          class="mb-1 block text-xs font-medium text-slate-500"
        >Ghi chú hoàn thành</label>
        <textarea
          id="task-complete-note"
          v-model="completionNote"
          rows="2"
          class="input w-full resize-y text-sm"
          placeholder="Tuỳ chọn — tóm tắt kết quả"
        />
      </div>

      <div
        v-if="previewTiming || previewSla"
        class="flex flex-wrap gap-2"
      >
        <span
          v-if="previewTiming"
          class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold"
          :class="getTaskSlaToneClass(previewTiming.color === 'emerald' ? 'ok' : previewTiming.color === 'amber' ? 'warn' : 'danger')"
        >
          {{ previewTiming.label }}
        </span>
        <span
          v-if="previewSla"
          class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold"
          :class="getTaskSlaToneClass(previewSla.color === 'emerald' ? 'ok' : 'danger')"
        >
          {{ previewSla.label }}
        </span>
      </div>
    </div>

    <div class="mt-6 flex flex-wrap justify-end gap-2">
      <button
        type="button"
        class="btn-secondary h-9 px-4 text-sm"
        :disabled="submitting"
        @click="close"
      >
        Huỷ
      </button>
      <button
        type="button"
        class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-sm"
        :disabled="submitting"
        @click="onSubmit"
      >
        <AppIcon
          name="done"
          :size="16"
        />
        Hoàn thành
      </button>
    </div>
  </Modal>
</template>
