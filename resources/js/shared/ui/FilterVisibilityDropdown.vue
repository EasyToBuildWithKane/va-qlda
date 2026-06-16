<script setup>
import { toRef } from 'vue';
import { useFixedDropdownAnchor, resolveAnchorElement } from '@/shared/composables/useFixedDropdownAnchor';

const props = defineProps({
    show: { type: Boolean, default: false },
    controls: { type: Array, required: true },
    modelValue: { type: Object, required: true },
    /** Template ref của nút / wrapper — panel teleport ra body */
    anchorRef: { type: Object, default: null },
    /** Prefix cho id/name checkbox (a11y) */
    inputIdPrefix: { type: String, default: 'va-filter-vis' },
});

const emit = defineEmits(['update:modelValue', 'persist']);

const { panelStyle } = useFixedDropdownAnchor(
    () => resolveAnchorElement(props.anchorRef),
    toRef(props, 'show'),
    { width: 224, zIndex: 120, preferDown: true },
);

function inputId(key) {
    return `${props.inputIdPrefix}-${key}`;
}

function onToggle(key, checked) {
    emit('update:modelValue', { ...props.modelValue, [key]: checked });
    emit('persist');
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        :style="panelStyle"
        class="flex max-h-[inherit] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-elevation-2 dark:border-slate-700 dark:bg-slate-900"
        data-filter-visibility-panel
      >
        <div class="border-b border-slate-100 px-4 py-2.5 dark:border-slate-700">
          <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Hiển thị trên thanh công cụ</span>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto px-2 py-2">
          <label
            v-for="f in controls"
            :key="f.key"
            :for="inputId(f.key)"
            class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800"
          >
            <input
              :id="inputId(f.key)"
              :name="inputId(f.key)"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand/30"
              :checked="modelValue[f.key]"
              @change="onToggle(f.key, $event.target.checked)"
            >
            <span class="text-sm text-slate-700 dark:text-slate-200">{{ f.label }}</span>
          </label>
        </div>
        <div class="shrink-0 border-t border-slate-100 px-4 py-2.5 dark:border-slate-700">
          <p class="text-[11px] leading-snug text-slate-400">
            Ô tìm kiếm luôn hiển thị. Bật/tắt bộ lọc bạn muốn thấy ở dòng dưới.
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
