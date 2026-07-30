<script setup>
import {
    computed, nextTick, onBeforeUnmount, onMounted, ref, watch,
} from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] }, // [{ code, name, local_department_id? }]
    placeholder: { type: String, default: 'Tìm phòng ban…' },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    invalid: { type: Boolean, default: false },
    id: { type: String, default: undefined },
    /** z-index panel (modal dùng ≥120). */
    panelZIndex: { type: Number, default: 120 },
});

const emit = defineEmits(['update:modelValue', 'select']);

const query = ref('');
const open = ref(false);
const rootRef = ref(null);
const inputRef = ref(null);
const panelRef = ref(null);
const dropdownStyle = ref({});
const openUp = ref(false);
const highlight = ref(0);

const selected = computed(() => (
    props.options.find((o) => String(o.code) === String(props.modelValue)) || null
));

const displayLabel = (o) => (o ? `${o.name} (${o.code})` : '');

watch(selected, (s) => {
    if (!open.value) query.value = s ? displayLabel(s) : '';
}, { immediate: true });

const filtered = computed(() => {
    const q = query.value.trim();
    const list = props.options;
    if (!q || (selected.value && q === displayLabel(selected.value))) {
        return list.slice(0, 80);
    }
    return list
        .filter((o) => matchesSearchQuery([o.name, o.code], q))
        .slice(0, 80);
});

watch(filtered, () => { highlight.value = 0; });

async function positionPanel() {
    await nextTick();
    const el = rootRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const panelMaxH = 280;
    const gap = 4;
    const spaceBelow = window.innerHeight - rect.bottom - gap;
    const spaceAbove = rect.top - gap;
    openUp.value = spaceBelow < panelMaxH && spaceAbove > spaceBelow;
    dropdownStyle.value = {
        position: 'fixed',
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        zIndex: props.panelZIndex,
        ...(openUp.value
            ? { bottom: `${window.innerHeight - rect.top + gap}px` }
            : { top: `${rect.bottom + gap}px` }),
    };
}

function choose(o) {
    emit('update:modelValue', o?.code ?? '');
    emit('select', o || null);
    query.value = o ? displayLabel(o) : '';
    open.value = false;
}

function clear() {
    if (props.disabled || !props.clearable) return;
    choose(null);
    nextTick(() => inputRef.value?.focus());
}

function onFocus() {
    if (props.disabled) return;
    open.value = true;
    if (selected.value) {
        query.value = '';
    }
}

function onInput() {
    open.value = true;
    // Typing clears selection until user picks again
    if (selected.value && query.value !== displayLabel(selected.value)) {
        emit('update:modelValue', '');
        emit('select', null);
    }
}

function onBlurSync() {
    // Restore label if still selected; otherwise keep typed text for continued search
    if (selected.value) {
        query.value = displayLabel(selected.value);
    }
}

function onKeydown(e) {
    if (!open.value && (e.key === 'ArrowDown' || e.key === 'Enter')) {
        open.value = true;
        return;
    }
    if (!open.value) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlight.value = Math.min(highlight.value + 1, Math.max(filtered.value.length - 1, 0));
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlight.value = Math.max(highlight.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const item = filtered.value[highlight.value];
        if (item) choose(item);
    } else if (e.key === 'Escape') {
        e.preventDefault();
        open.value = false;
        onBlurSync();
    }
}

function onClickOutside(e) {
    if (rootRef.value?.contains(e.target) || panelRef.value?.contains(e.target)) return;
    open.value = false;
    onBlurSync();
}

let scrollListener = null;

watch(open, async (isOpen) => {
    if (isOpen) {
        await positionPanel();
        scrollListener = () => positionPanel();
        window.addEventListener('scroll', scrollListener, true);
        window.addEventListener('resize', scrollListener);
    } else if (scrollListener) {
        window.removeEventListener('scroll', scrollListener, true);
        window.removeEventListener('resize', scrollListener);
        scrollListener = null;
    }
});

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onClickOutside);
    if (scrollListener) {
        window.removeEventListener('scroll', scrollListener, true);
        window.removeEventListener('resize', scrollListener);
    }
});
</script>

<template>
  <div
    ref="rootRef"
    class="relative"
  >
    <div class="relative">
      <AppIcon
        name="department"
        :size="15"
        class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"
      />
      <input
        :id="id"
        ref="inputRef"
        v-model="query"
        type="text"
        class="input h-9 w-full pl-8 pr-8 text-sm"
        :class="[
          invalid ? 'border-rose-300' : '',
          disabled ? 'cursor-not-allowed bg-slate-50 text-slate-500' : '',
        ]"
        :placeholder="placeholder"
        :disabled="disabled"
        autocomplete="off"
        role="combobox"
        :aria-expanded="open"
        aria-autocomplete="list"
        @focus="onFocus"
        @input="onInput"
        @keydown="onKeydown"
      >
      <button
        v-if="clearable && !disabled && (modelValue || query)"
        type="button"
        class="absolute inset-y-0 right-1.5 flex items-center rounded p-1 text-slate-300 hover:text-rose-500"
        title="Bỏ chọn"
        aria-label="Bỏ chọn phòng ban"
        @click="clear"
      >
        <AppIcon
          name="close"
          :size="14"
        />
      </button>
    </div>

    <Teleport to="body">
      <div
        v-if="open && !disabled"
        ref="panelRef"
        :style="dropdownStyle"
        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-elevation-2"
        role="listbox"
      >
        <ul class="max-h-56 overflow-y-auto py-1">
          <li
            v-for="(o, idx) in filtered"
            :key="o.code"
            role="option"
            class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm transition-colors"
            :class="[
              String(o.code) === String(modelValue) ? 'bg-brand/5 text-brand' : 'text-slate-700',
              idx === highlight ? 'bg-slate-50' : 'hover:bg-slate-50',
            ]"
            :aria-selected="String(o.code) === String(modelValue)"
            @mousedown.prevent="choose(o)"
            @mouseenter="highlight = idx"
          >
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500">
              <AppIcon
                name="department"
                :size="14"
              />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate font-medium">{{ o.name }}</span>
              <span class="block truncate font-mono text-[11px] text-slate-400">{{ o.code }}</span>
            </span>
            <AppIcon
              v-if="String(o.code) === String(modelValue)"
              name="check"
              :size="14"
              class="shrink-0 text-brand"
            />
          </li>
          <li
            v-if="!filtered.length"
            class="px-3 py-3 text-center text-sm text-slate-400"
          >
            Không tìm thấy phòng ban.
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
