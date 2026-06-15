<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    modelValue: { type: String, default: '' },
    suggestions: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Nhóm kỹ năng' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const inputRef = ref(null);
const panelRef = ref(null);
const highlightIndex = ref(-1);

const query = computed({
    get: () => props.modelValue ?? '',
    set: (v) => emit('update:modelValue', v),
});

const filtered = computed(() => {
    const q = query.value.trim();
    const list = props.suggestions.filter((s) => typeof s === 'string' && s.trim() !== '');
    if (!q) {
        return list;
    }
    return list.filter((s) => matchesSearchQuery([s], q));
});

const showPanel = computed(() => open.value && filtered.value.length > 0);

function openPanel() {
    open.value = true;
    highlightIndex.value = -1;
}

function closePanel() {
    open.value = false;
    highlightIndex.value = -1;
}

function pick(value) {
    query.value = value;
    closePanel();
    nextTick(() => inputRef.value?.focus());
}

function onKeydown(e) {
    if (!showPanel.value) {
        if (e.key === 'ArrowDown' && filtered.value.length) {
            openPanel();
            highlightIndex.value = 0;
            e.preventDefault();
        }
        return;
    }
    if (e.key === 'Escape') {
        closePanel();
        return;
    }
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightIndex.value = Math.min(highlightIndex.value + 1, filtered.value.length - 1);
        return;
    }
    if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightIndex.value = Math.max(highlightIndex.value - 1, 0);
        return;
    }
    if (e.key === 'Enter' && highlightIndex.value >= 0) {
        e.preventDefault();
        pick(filtered.value[highlightIndex.value]);
    }
}

function onBlur() {
    window.setTimeout(() => closePanel(), 150);
}

function onPointerDownOutside(e) {
    const t = e.target;
    if (inputRef.value?.contains(t) || panelRef.value?.contains(t)) {
        return;
    }
    closePanel();
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('mousedown', onPointerDownOutside);
    } else {
        document.removeEventListener('mousedown', onPointerDownOutside);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onPointerDownOutside);
});
</script>

<template>
  <div class="relative min-w-0">
    <input
      :id="id"
      ref="inputRef"
      v-model="query"
      type="text"
      class="input h-10 w-full text-sm"
      :placeholder="placeholder"
      autocomplete="off"
      role="combobox"
      :aria-expanded="showPanel"
      aria-autocomplete="list"
      @focus="openPanel"
      @input="openPanel"
      @keydown="onKeydown"
      @blur="onBlur"
    >
    <ul
      v-if="showPanel"
      ref="panelRef"
      class="absolute left-0 right-0 z-[120] mt-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200/90 bg-white py-1 shadow-elevation-2"
      role="listbox"
    >
      <li
        v-for="(item, idx) in filtered"
        :key="item"
        role="option"
        :aria-selected="idx === highlightIndex"
      >
        <button
          type="button"
          class="flex w-full px-3 py-2 text-left text-[13px] text-slate-700 transition-colors hover:bg-slate-50"
          :class="idx === highlightIndex ? 'bg-brand/5 text-brand' : ''"
          @mousedown.prevent="pick(item)"
        >
          {{ item }}
        </button>
      </li>
    </ul>
  </div>
</template>
