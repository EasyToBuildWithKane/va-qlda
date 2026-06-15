<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    suggestions: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
    maxTags: { type: Number, default: 20 },
});

const emit = defineEmits(['update:modelValue']);

const draft = ref('');

const tags = computed({
    get: () => (Array.isArray(props.modelValue) ? props.modelValue : []),
    set: (val) => emit('update:modelValue', val),
});

const suggestionList = computed(() => {
    const names = props.suggestions
        .map((t) => (typeof t === 'string' ? t : t?.name))
        .filter((n) => typeof n === 'string' && n.trim() !== '');
    const selected = new Set(tags.value.map((t) => t.toLowerCase()));
    return [...new Set(names)].filter((n) => !selected.has(n.toLowerCase()));
});

const filteredSuggestions = computed(() => {
    const q = draft.value.trim();
    if (!q) return suggestionList.value.slice(0, 12);
    return suggestionList.value
        .filter((name) => matchesSearchQuery([name], q))
        .slice(0, 12);
});

function normalizeToken(raw) {
    return String(raw ?? '').trim().replace(/,+$/, '').trim();
}

function addToken(raw) {
    const token = normalizeToken(raw);
    if (!token || props.disabled) return;
    if (tags.value.length >= props.maxTags) return;
    const next = [...tags.value];
    const exists = next.some((t) => t.toLowerCase() === token.toLowerCase());
    if (!exists) {
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

function toggleSuggestion(name) {
    addToken(name);
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
    const set = new Set(tags.value.map((t) => t.toLowerCase()));
    const next = [...tags.value];
    for (const p of parts) {
        if (next.length >= props.maxTags) break;
        const key = p.toLowerCase();
        if (!set.has(key)) {
            set.add(key);
            next.push(p);
        }
    }
    tags.value = next;
    draft.value = '';
}

function onBlur() {
    if (draft.value.trim()) addToken(draft.value);
}
</script>

<template>
  <div>
    <div class="mb-1.5 flex items-center justify-between gap-2">
      <span class="label mb-0 flex items-center gap-1.5">
        Thẻ
        <FieldTooltip
          wide
          text="Gắn thẻ để lọc và gợi ý bài liên quan. Enter hoặc dấu phẩy để thêm; bấm gợi ý bên dưới để chọn nhanh."
        />
      </span>
      <span
        class="text-[11px] tabular-nums"
        :class="tags.length ? 'font-medium text-brand' : 'text-slate-400'"
      >
        {{ tags.length }}/{{ maxTags }}
      </span>
    </div>

    <div
      class="rounded-input border border-slate-300 bg-white transition focus-within:border-brand focus-within:ring-1 focus-within:ring-brand/30"
      :class="{ 'opacity-60': disabled, 'border-danger ring-1 ring-danger/20': error }"
    >
      <div class="flex min-h-[2.75rem] flex-wrap items-center gap-1.5 px-2.5 py-2">
        <span
          v-for="(tag, index) in tags"
          :key="`${tag}-${index}`"
          class="inline-flex max-w-full items-center gap-1 rounded-full border border-brand/20 bg-brand/10 pl-2.5 pr-1 py-0.5 text-[12px] font-medium text-brand shadow-sm"
        >
          <span class="truncate">#{{ tag }}</span>
          <button
            v-if="!disabled"
            type="button"
            class="grid h-5 w-5 shrink-0 place-items-center rounded-full text-brand/80 hover:bg-brand/20"
            :aria-label="`Xoá thẻ ${tag}`"
            @click="removeAt(index)"
          >
            <AppIcon
              name="close"
              :size="10"
            />
          </button>
        </span>
        <input
          v-model="draft"
          type="text"
          class="min-w-[8rem] flex-1 border-0 bg-transparent py-0.5 text-sm outline-none placeholder:text-slate-400 disabled:cursor-not-allowed"
          :placeholder="tags.length ? 'Thêm thẻ…' : 'Nhập thẻ, Enter hoặc dấu phẩy'"
          :disabled="disabled || tags.length >= maxTags"
          autocomplete="off"
          aria-label="Thêm thẻ"
          @keydown="onKeydown"
          @paste="onPaste"
          @blur="onBlur"
        >
      </div>

      <div
        v-if="filteredSuggestions.length && !disabled"
        class="border-t border-slate-100 bg-slate-50/80 px-2.5 py-2"
      >
        <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400">
          Gợi ý
        </p>
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="name in filteredSuggestions"
            :key="name"
            type="button"
            class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 transition hover:border-brand/40 hover:bg-brand/5 hover:text-brand"
            @mousedown.prevent="toggleSuggestion(name)"
          >
            <AppIcon
              name="add"
              :size="10"
            />
            {{ name }}
          </button>
        </div>
      </div>
    </div>

    <p
      v-if="error"
      class="mt-1 text-xs text-danger"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>
