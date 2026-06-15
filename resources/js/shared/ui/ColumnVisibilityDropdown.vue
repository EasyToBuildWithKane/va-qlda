<script setup>
import { computed, toRef } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';

const props = defineProps({
    show: { type: Boolean, default: false },
    columns: { type: Array, required: true },
    modelValue: { type: Object, required: true },
    fixedLabels: { type: Array, default: () => [] },
    /** Template ref của nút / wrapper — panel teleport ra body */
    anchorRef: { type: [Object, null], default: null },
    /** Prefix cho id/name checkbox (a11y) */
    inputIdPrefix: { type: String, default: 'va-col-vis' },
});

const emit = defineEmits(['update:modelValue', 'persist']);

const panelWidth = 224;

/** Có anchor từ parent → teleport; không có → panel absolute trong wrapper */
const useTeleportedPanel = computed(() => props.anchorRef != null);

const { panelStyle } = useFixedDropdownAnchor(
    () => props.anchorRef,
    toRef(props, 'show'),
    { width: panelWidth, zIndex: 85, preferDown: true },
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
  <Teleport
    v-if="useTeleportedPanel"
    to="body"
  >
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        :style="panelStyle"
        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-elevation-2 dark:border-slate-700 dark:bg-slate-900"
        data-column-visibility-panel
      >
        <div class="border-b border-slate-100 px-4 py-2.5 dark:border-slate-700">
          <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Cột hiển thị</span>
        </div>
        <div
          v-if="fixedLabels.length"
          class="border-b border-slate-100 px-4 py-2 dark:border-slate-700"
        >
          <div
            v-for="label in fixedLabels"
            :key="label"
            class="flex items-center justify-between rounded-lg px-2 py-1.5 opacity-50"
          >
            <span class="text-sm text-slate-600 dark:text-slate-300">{{ label }}</span>
            <AppIcon
              name="check"
              :size="14"
              class="text-emerald-500"
            />
          </div>
        </div>
        <div class="max-h-64 overflow-y-auto px-2 py-2">
          <label
            v-for="col in columns"
            :key="col.key"
            :for="inputId(col.key)"
            class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800"
          >
            <input
              :id="inputId(col.key)"
              :name="inputId(col.key)"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand/30"
              :checked="modelValue[col.key]"
              @change="onToggle(col.key, $event.target.checked)"
            >
            <span class="text-sm text-slate-700 dark:text-slate-200">{{ col.label }}</span>
          </label>
        </div>
        <div class="border-t border-slate-100 px-4 py-2 dark:border-slate-700">
          <p class="text-[11px] text-slate-400">
            Cột «Thao tác» luôn hiển thị
          </p>
        </div>
      </div>
    </Transition>
  </Teleport>
  <Transition
    v-else
    enter-active-class="transition duration-150 ease-out"
    enter-from-class="opacity-0 scale-95 -translate-y-1"
    leave-active-class="transition duration-100 ease-in"
    leave-to-class="opacity-0 scale-95 -translate-y-1"
  >
    <div
      v-if="show"
      class="absolute left-0 top-full z-50 mt-1.5 w-56 origin-top-left rounded-xl border border-slate-200 bg-white shadow-elevation-2 sm:left-auto sm:right-0 sm:origin-top-right"
      data-column-visibility-panel
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
      <div class="max-h-64 overflow-y-auto px-2 py-2">
        <label
          v-for="col in columns"
          :key="col.key"
          :for="inputId(col.key)"
          class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2.5 py-1.5 hover:bg-slate-50"
        >
          <input
            :id="inputId(col.key)"
            :name="inputId(col.key)"
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
</template>
