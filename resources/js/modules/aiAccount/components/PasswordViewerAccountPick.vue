<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    candidates: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['pick']);

const inputRef = ref(null);
const panelRef = ref(null);
const open = ref(false);
const query = ref('');
const panelStyle = ref({});

const filtered = computed(() => {
    const q = query.value.trim();
    const list = props.candidates ?? [];
    if (!q) return list.slice(0, 40);
    return list
        .filter((c) => matchesSearchQuery([c.label, c.name, c.email, c.department], q))
        .slice(0, 40);
});

function pick(item) {
    query.value = item.name ?? item.label ?? '';
    open.value = false;
    emit('pick', item);
}

function onInput() {
    open.value = true;
}

async function onFocus() {
    if (props.disabled) return;
    open.value = true;
    await nextTick();
    const el = inputRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    panelStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        zIndex: 9999,
    };
}

function onDocMousedown(e) {
    if (panelRef.value?.contains(e.target) || inputRef.value?.contains(e.target)) return;
    open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('mousedown', onDocMousedown);
    } else {
        document.removeEventListener('mousedown', onDocMousedown);
    }
});

onBeforeUnmount(() => document.removeEventListener('mousedown', onDocMousedown));
</script>

<template>
  <div class="relative min-w-0">
    <label
      for="password-viewer-pick"
      class="label mb-0 flex min-h-[1.25rem] items-center gap-1"
    >
      Thêm thành viên
    </label>
    <div class="relative">
      <input
        id="password-viewer-pick"
        ref="inputRef"
        v-model="query"
        type="text"
        class="input h-10 w-full pr-10"
        placeholder="Tìm tên hoặc email đăng nhập…"
        autocomplete="off"
        :disabled="disabled"
        @focus="onFocus"
        @input="onInput"
      >
      <AppIcon
        name="search"
        :size="16"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
      />
    </div>

    <Teleport to="body">
      <ul
        v-if="open && filtered.length"
        ref="panelRef"
        class="max-h-52 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
        :style="panelStyle"
      >
        <li
          v-for="item in filtered"
          :key="item.id"
          class="cursor-pointer px-3 py-2 text-sm hover:bg-brand-soft"
          @mousedown.prevent="pick(item)"
        >
          <div class="font-medium text-slate-800">
            {{ item.name }}
          </div>
          <div class="text-xs text-slate-500">
            {{ item.email }}
            <span v-if="item.department"> · {{ item.department }}</span>
          </div>
        </li>
      </ul>
    </Teleport>
  </div>
</template>
