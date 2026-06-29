<script setup>
import { ref, nextTick, watch, toRef } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useFixedDropdownAnchor, resolveAnchorElement } from '@/shared/composables/useFixedDropdownAnchor';

const props = defineProps({
    open: { type: Boolean, default: false },
    taskTitle: { type: String, default: '' },
    anchorRef: { type: Object, default: null },
});

const emit = defineEmits(['submit', 'close']);

const hours = ref('');
const note = ref('');
const hoursInput = ref(null);

const { panelStyle } = useFixedDropdownAnchor(
    () => resolveAnchorElement(props.anchorRef),
    toRef(props, 'open'),
    { width: 256, zIndex: 120, preferDown: true, maxHeight: 320 },
);

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            hours.value = '';
            note.value = '';
            await nextTick();
            hoursInput.value?.focus();
        }
    },
);

const valid = () => {
    const h = Number(hours.value);
    return Number.isFinite(h) && h >= 0.25 && h <= 24;
};

const submit = () => {
    if (!valid()) return;
    emit('submit', { hours: Number(hours.value), note: note.value.trim() || null });
};
</script>

<template>
  <Teleport to="body">
    <button
      v-if="open"
      type="button"
      class="fixed inset-0 z-[110] cursor-default bg-transparent"
      aria-label="Đóng"
      @click="emit('close')"
    />
    <div
      v-if="open"
      :style="{ ...panelStyle, overflowY: 'auto' }"
      class="rounded-xl border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-900"
      role="dialog"
      aria-label="Ghi giờ làm nhanh"
      @keydown.esc="emit('close')"
    >
      <div class="mb-2 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
        <AppIcon
          name="worklog"
          :size="13"
        />
        Ghi giờ hôm nay
      </div>

      <label class="block text-[11px] font-medium text-slate-500">Số giờ</label>
      <input
        ref="hoursInput"
        v-model="hours"
        type="number"
        min="0.25"
        max="24"
        step="0.25"
        inputmode="decimal"
        placeholder="VD: 1.5"
        class="mt-0.5 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand focus:ring-1 focus:ring-brand dark:border-slate-700 dark:bg-slate-800"
        @keydown.enter.prevent="submit"
      >

      <label class="mt-2 block text-[11px] font-medium text-slate-500">Ghi chú (tuỳ chọn)</label>
      <input
        v-model="note"
        type="text"
        maxlength="255"
        placeholder="Bạn đã làm gì?"
        class="mt-0.5 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm outline-none focus:border-brand focus:ring-1 focus:ring-brand dark:border-slate-700 dark:bg-slate-800"
        @keydown.enter.prevent="submit"
      >

      <div class="mt-3 flex items-center justify-end gap-2">
        <button
          type="button"
          class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="button"
          :disabled="!valid()"
          class="inline-flex items-center gap-1 rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-brand/90 disabled:cursor-not-allowed disabled:opacity-40"
          @click="submit"
        >
          <AppIcon
            name="save"
            :size="13"
          />
          Lưu
        </button>
      </div>
    </div>
  </Teleport>
</template>
