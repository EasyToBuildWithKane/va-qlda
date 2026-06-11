<script setup>
/* eslint-disable vue/no-v-html -- markdown preview from controlled editor input */
import { ref, computed, nextTick } from 'vue';
import MarkdownIt from 'markdown-it';
import InfoTooltip from './InfoTooltip.vue';

// html:false (default) keeps raw HTML escaped — safe to v-html the output.
const md = new MarkdownIt({ breaks: true, linkify: true });

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, required: true },
    rows: { type: Number, default: 4 },
    max: { type: Number, default: 5000 },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    tooltip: { type: String, default: '' },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const preview = ref(false);
const textarea = ref(null);
const html = computed(() => md.render(props.modelValue || ''));
const count = computed(() => (props.modelValue || '').length);
const filled = computed(() => (props.modelValue || '').trim().length > 0);

// Lightweight markdown toolbar: wrap the selection or prefix the current line.
const toolbar = [
    { key: 'bold', label: 'B', title: 'In đậm', wrap: ['**', '**'] },
    { key: 'italic', label: 'I', title: 'In nghiêng', wrap: ['_', '_'], italic: true },
    { key: 'code', label: '</>', title: 'Mã', wrap: ['`', '`'] },
    { key: 'list', label: '•', title: 'Danh sách', prefix: '- ' },
    { key: 'todo', label: '☑', title: 'Việc cần làm', prefix: '- [ ] ' },
];

const apply = async (tool) => {
    const el = textarea.value;
    if (!el) return;
    const value = props.modelValue || '';
    const start = el.selectionStart ?? value.length;
    const end = el.selectionEnd ?? value.length;
    let next = value;
    let caret = end;

    if (tool.wrap) {
        const [a, b] = tool.wrap;
        next = value.slice(0, start) + a + value.slice(start, end) + b + value.slice(end);
        caret = end + a.length + (start === end ? 0 : b.length);
    } else if (tool.prefix) {
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        next = value.slice(0, lineStart) + tool.prefix + value.slice(lineStart);
        caret = end + tool.prefix.length;
    }

    emit('update:modelValue', next);
    await nextTick();
    el.focus();
    el.setSelectionRange(caret, caret);
};
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-1">
      <span class="label mb-0 flex items-center gap-1.5">
        <span
          class="inline-block h-1.5 w-1.5 rounded-full"
          :class="filled ? 'bg-success' : 'bg-slate-300'"
        />
        {{ label }}
        <span
          v-if="required"
          class="text-danger"
          title="Bắt buộc"
        >*</span>
        <InfoTooltip
          v-if="tooltip"
          :text="tooltip"
        />
      </span>
      <div class="flex items-center gap-3 text-xs">
        <span class="text-slate-400">{{ count }}/{{ max }}</span>
        <button
          type="button"
          class="text-brand hover:underline"
          @click="preview = !preview"
        >
          {{ preview ? 'Soạn thảo' : 'Xem trước' }}
        </button>
      </div>
    </div>

    <p
      v-if="hint"
      class="mb-1.5 text-xs text-slate-400"
    >
      {{ hint }}
    </p>

    <!-- Toolbar (edit mode only) -->
    <div
      v-if="!preview"
      class="mb-1 flex items-center gap-1"
    >
      <button
        v-for="tool in toolbar"
        :key="tool.key"
        type="button"
        class="grid h-7 min-w-7 place-items-center rounded-input border border-slate-200 px-1.5 text-xs text-slate-500 transition hover:border-brand hover:bg-brand-50 hover:text-brand"
        :class="{ 'italic': tool.italic, 'font-bold': tool.key === 'bold' }"
        :title="tool.title"
        @click="apply(tool)"
      >
        {{ tool.label }}
      </button>
    </div>

    <div
      v-if="preview"
      class="input min-h-[6rem] bg-slate-50 whitespace-normal markdown-body"
      v-html="html"
    />
    <textarea
      v-else
      ref="textarea"
      :rows="rows"
      :maxlength="max"
      :placeholder="placeholder"
      class="input font-mono text-sm"
      :value="modelValue"
      @input="emit('update:modelValue', $event.target.value)"
    />

    <p
      v-if="error"
      class="mt-1 text-sm text-danger"
    >
      {{ error }}
    </p>
  </div>
</template>
