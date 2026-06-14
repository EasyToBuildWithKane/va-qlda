<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Nhập rồi Enter hoặc dấu phẩy' },
    id: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const draft = ref('');

const tags = computed({
    get: () => (Array.isArray(props.modelValue) ? props.modelValue : []),
    set: (val) => emit('update:modelValue', val),
});

function normalizeToken(raw) {
    return String(raw ?? '').trim().replace(/,+$/, '').trim();
}

function addToken(raw) {
    const token = normalizeToken(raw);
    if (!token || props.disabled) return;
    const next = [...tags.value];
    if (!next.includes(token)) {
        next.push(token);
        tags.value = next;
    }
    draft.value = '';
}

function removeAt(index) {
    if (props.disabled) return;
    const next = [...tags.value];
    next.splice(index, 1);
    tags.value = next;
}

function onKeydown(e) {
    if (props.disabled) return;
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addToken(draft.value);
        return;
    }
    if (e.key === 'Backspace' && draft.value === '' && tags.value.length) {
        removeAt(tags.value.length - 1);
    }
}

function onPaste(e) {
    const text = e.clipboardData?.getData('text') ?? '';
    if (!text.includes(',') && !text.includes('\n')) return;
    e.preventDefault();
    const parts = text.split(/[\n,]+/).map(normalizeToken).filter(Boolean);
    const set = new Set(tags.value);
    for (const p of parts) set.add(p);
    tags.value = [...set];
    draft.value = '';
}

function onBlur() {
    if (draft.value.trim()) addToken(draft.value);
}
</script>

<template>
  <div
    class="input flex min-h-[2.75rem] w-full flex-wrap items-center gap-1.5 px-2 py-1.5 disabled:bg-slate-50"
    :class="{ 'opacity-60': disabled }"
  >
    <span
      v-for="(tag, index) in tags"
      :key="`${tag}-${index}`"
      class="inline-flex max-w-full items-center gap-1 rounded-md bg-brand/10 px-2 py-0.5 text-[12px] font-medium text-brand"
    >
      <span class="truncate">{{ tag }}</span>
      <button
        v-if="!disabled"
        type="button"
        class="grid h-4 w-4 shrink-0 place-items-center rounded text-brand/80 hover:bg-brand/20"
        :aria-label="`Xoá ${tag}`"
        @click="removeAt(index)"
      >
        <AppIcon
          name="close"
          :size="10"
        />
      </button>
    </span>
    <input
      :id="id"
      v-model="draft"
      type="text"
      class="min-w-[8rem] flex-1 border-0 bg-transparent py-1 text-sm outline-none placeholder:text-slate-400 disabled:cursor-not-allowed"
      :placeholder="tags.length ? '' : placeholder"
      :disabled="disabled"
      autocomplete="off"
      @keydown="onKeydown"
      @paste="onPaste"
      @blur="onBlur"
    >
  </div>
</template>
