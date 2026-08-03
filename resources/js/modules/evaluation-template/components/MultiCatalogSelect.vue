<script setup>
import {
    computed, nextTick, onBeforeUnmount, onMounted, ref, watch,
} from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    modelValue: { type: Array, default: () => [] }, // [{ code, name, ... }]
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Tìm và chọn…' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const open = ref(false);
const rootRef = ref(null);
const panelRef = ref(null);
const dropdownStyle = ref({});
const highlight = ref(0);

const selectedCodes = computed(() => new Set((props.modelValue || []).map((x) => String(x.code))));

const availableCount = computed(() => (props.options || []).length);

const filtered = computed(() => {
    const q = query.value.trim();
    const list = (props.options || []).filter((o) => !selectedCodes.value.has(String(o.code)));
    if (!q) return list.slice(0, 80);
    return list.filter((o) => matchesSearchQuery([o.name, o.code], q)).slice(0, 80);
});

watch(filtered, () => { highlight.value = 0; });

async function positionPanel() {
    await nextTick();
    const el = rootRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const panelH = 240;
    const spaceBelow = window.innerHeight - rect.bottom;
    const openUp = spaceBelow < panelH && rect.top > spaceBelow;
    dropdownStyle.value = {
        position: 'fixed',
        left: `${rect.left}px`,
        width: `${Math.max(rect.width, 220)}px`,
        top: openUp ? 'auto' : `${rect.bottom + 4}px`,
        bottom: openUp ? `${window.innerHeight - rect.top + 4}px` : 'auto',
        zIndex: 130,
    };
}

function add(opt) {
    if (!opt || selectedCodes.value.has(String(opt.code))) return;
    emit('update:modelValue', [...(props.modelValue || []), {
        code: opt.code,
        name: opt.name,
        hrm_uuid: opt.hrm_uuid ?? null,
        source: opt.source ?? 'directory',
    }]);
    query.value = '';
    open.value = true;
}

function remove(code) {
    emit('update:modelValue', (props.modelValue || []).filter((x) => String(x.code) !== String(code)));
}

function onFocus() {
    if (props.disabled) return;
    open.value = true;
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
        if (item) add(item);
    } else if (e.key === 'Escape') {
        open.value = false;
    }
}

function onClickOutside(e) {
    if (rootRef.value?.contains(e.target) || panelRef.value?.contains(e.target)) return;
    open.value = false;
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
    class="space-y-2"
  >
    <div
      v-if="modelValue?.length"
      class="flex flex-wrap gap-1.5"
    >
      <span
        v-for="item in modelValue"
        :key="item.code"
        class="inline-flex max-w-full items-center gap-1 rounded-md bg-brand/10 px-2 py-1 text-xs font-medium text-brand ring-1 ring-inset ring-brand/15"
      >
        <span class="truncate">{{ item.name }}</span>
        <button
          v-if="!disabled"
          type="button"
          class="rounded p-0.5 hover:bg-brand/20"
          :aria-label="`Bỏ ${item.name}`"
          @click="remove(item.code)"
        >
          <AppIcon
            name="close"
            :size="12"
          />
        </button>
      </span>
    </div>

    <div
      v-if="!disabled"
      class="relative"
    >
      <AppIcon
        name="search"
        :size="14"
        class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"
      />
      <input
        v-model="query"
        type="text"
        class="input h-10 w-full pl-8 pr-16 text-sm"
        :placeholder="availableCount ? placeholder : 'Chưa có dữ liệu'"
        :disabled="!availableCount"
        autocomplete="off"
        @focus="onFocus"
        @input="open = true"
        @keydown="onKeydown"
      >
      <span
        v-if="availableCount"
        class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-slate-500"
      >
        {{ availableCount }}
      </span>
    </div>

    <Teleport to="body">
      <div
        v-if="open && !disabled && availableCount"
        ref="panelRef"
        :style="dropdownStyle"
        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-elevation-2"
      >
        <ul class="max-h-56 overflow-y-auto py-1">
          <li
            v-for="(o, idx) in filtered"
            :key="o.code"
            class="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
            :class="idx === highlight ? 'bg-slate-50' : ''"
            @mousedown.prevent="add(o)"
            @mouseenter="highlight = idx"
          >
            <span class="min-w-0 flex-1">
              <span class="block truncate font-medium">{{ o.name }}</span>
              <span class="block truncate font-mono text-[11px] text-slate-400">{{ o.code }}</span>
            </span>
            <AppIcon
              name="plus"
              :size="14"
              class="shrink-0 text-brand"
            />
          </li>
          <li
            v-if="!filtered.length"
            class="px-3 py-3 text-center text-sm text-slate-400"
          >
            Không còn lựa chọn phù hợp.
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
