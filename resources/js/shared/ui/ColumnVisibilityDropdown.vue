<script setup>
import { toRef } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useAnchoredDropdownStyle } from '@/shared/composables/useAnchoredDropdownStyle';

const props = defineProps({
    show: { type: Boolean, default: false },
    columns: { type: Array, required: true },
    modelValue: { type: Object, required: true },
    fixedLabels: { type: Array, default: () => [] },
    anchor: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'persist']);

const showRef = toRef(props, 'show');
const { panelStyle } = useAnchoredDropdownStyle(props.anchor, showRef);

function onToggle(key, checked) {
    emit('update:modelValue', { ...props.modelValue, [key]: checked });
    emit('persist');
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95 -translate-y-1"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="opacity-0 scale-95 -translate-y-1"
    >
      <div
        v-if="show"
        data-va-anchored-dropdown
        :style="panelStyle"
        class="max-h-[min(16rem,70vh)] overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-elevation-2"
        @mousedown.stop
      >
        <div class="border-b border-slate-100 px-4 py-2.5">
          <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Cột hiển thị</span>
        </div>
        <div
          v-if="fixedLabels.length"
          class="border-b border-slate-100 px-4 py-2"
        >
          <div
            v-for="label in fixedLabels"
            :key="label"
            class="flex items-center justify-between rounded-lg px-2 py-1.5 opacity-50"
          >
            <span class="text-sm text-slate-600">{{ label }}</span>
            <AppIcon
              name="check"
              :size="14"
              class="text-emerald-500"
            />
          </div>
        </div>
        <div class="px-2 py-2">
          <label
            v-for="col in columns"
            :key="col.key"
            class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-slate-50"
          >
            <input
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand/30"
              :checked="modelValue[col.key]"
              @change="onToggle(col.key, $event.target.checked)"
            >
            <span class="text-sm text-slate-700">{{ col.label }}</span>
          </label>
        </div>
        <div class="border-t border-slate-100 px-4 py-2">
          <p class="text-[11px] text-slate-400">
            Cột «Thao tác» luôn hiển thị
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
