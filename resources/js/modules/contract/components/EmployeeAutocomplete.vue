<script setup>
import {
    ref, computed, watch, onMounted, onBeforeUnmount,
} from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    options: { type: Array, default: () => [] }, // [{ id, name, email? }]
    placeholder: { type: String, default: 'Gõ tên hoặc email…' },
    id: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const open = ref(false);
const rootRef = ref(null);

const selected = computed(() => props.options.find((o) => o.id === props.modelValue) || null);

// Hiển thị tên đã chọn khi không gõ tìm.
watch(selected, (s) => { if (!open.value) query.value = s ? s.name : ''; }, { immediate: true });

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q || (selected.value && q === selected.value.name.toLowerCase())) return props.options.slice(0, 50);
    return props.options
        .filter((o) => o.name.toLowerCase().includes(q) || (o.email || '').toLowerCase().includes(q))
        .slice(0, 50);
});

function choose(o) {
    emit('update:modelValue', o.id);
    query.value = o.name;
    open.value = false;
}

function clear() {
    emit('update:modelValue', null);
    query.value = '';
}

function onFocus() {
    open.value = true;
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
        :id="id"
        v-model="query"
        :placeholder="placeholder"
        class="input pr-8"
        autocomplete="off"
        @focus="onFocus"
      >
      <button
        v-if="selected"
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
    </div>

    <div
      v-if="open"
      class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-card border border-slate-200 bg-white py-1 shadow-elevation-2"
    >
      <button
        v-for="o in filtered"
        :key="o.id"
        type="button"
        class="flex w-full flex-col items-start px-3 py-1.5 text-left hover:bg-brand/5"
        :class="o.id === modelValue ? 'bg-brand/5' : ''"
        @click="choose(o)"
      >
        <span class="text-sm text-slate-700">{{ o.name }}</span>
        <span
          v-if="o.email"
          class="text-[11px] text-slate-400"
        >{{ o.email }}</span>
      </button>
      <p
        v-if="!filtered.length"
        class="px-3 py-2 text-xs text-slate-400"
      >
        Không tìm thấy nhân sự.
      </p>
    </div>
  </div>
</template>
