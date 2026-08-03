<script setup>
import {
    ref, computed, watch, onMounted, onBeforeUnmount,
} from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Gõ tên, mã hoặc email…' },
    inputClass: { type: String, default: 'input h-10 w-full text-sm pr-8' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'select']);

const query = ref('');
const open = ref(false);
const rootRef = ref(null);

const selected = computed(() => props.options.find((o) => o.id === props.modelValue) || null);

watch(selected, (s) => {
    if (!open.value) query.value = s ? s.name : '';
}, { immediate: true });

const filtered = computed(() => {
    const q = query.value.trim();
    if (!q || (selected.value && q === selected.value.name)) {
        return props.options.slice(0, 80);
    }
    return props.options
        .filter((o) => matchesSearchQuery([o.name, o.code, o.email, o.department_name], q))
        .slice(0, 80);
});

function choose(o) {
    emit('update:modelValue', o.id);
    emit('select', o);
    query.value = o.name;
    open.value = false;
}

function clear() {
    emit('update:modelValue', null);
    emit('select', null);
    query.value = '';
}

function onFocus() {
    if (!props.disabled) open.value = true;
}

function onClickOutside(e) {
    if (rootRef.value && !rootRef.value.contains(e.target)) {
        open.value = false;
        query.value = selected.value ? selected.value.name : '';
    }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
  <div
    ref="rootRef"
    class="relative"
  >
    <div class="relative">
      <input
        v-model="query"
        :placeholder="placeholder"
        :class="inputClass"
        :disabled="disabled"
        autocomplete="off"
        @focus="onFocus"
      >
      <button
        v-if="selected && !disabled"
        type="button"
        class="absolute inset-y-0 right-2 flex items-center text-slate-300 hover:text-slate-500"
        title="Bỏ chọn"
        @click="clear"
      >
        <AppIcon
          name="close"
          :size="14"
        />
      </button>
      <span
        v-else
        class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-slate-300"
      >
        <AppIcon
          name="search"
          :size="14"
        />
      </span>
    </div>
    <ul
      v-if="open && !disabled"
      class="absolute z-30 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
    >
      <li
        v-for="o in filtered"
        :key="o.id"
      >
        <button
          type="button"
          class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm hover:bg-brand/5"
          @click="choose(o)"
        >
          <span class="font-medium text-slate-800">{{ o.name }}</span>
          <span class="text-[11px] text-slate-400">
            {{ [o.code, o.email, o.department_name].filter(Boolean).join(' · ') }}
          </span>
        </button>
      </li>
      <li
        v-if="filtered.length === 0"
        class="px-3 py-2 text-sm text-slate-400"
      >
        Không tìm thấy nhân sự
      </li>
    </ul>
  </div>
</template>
