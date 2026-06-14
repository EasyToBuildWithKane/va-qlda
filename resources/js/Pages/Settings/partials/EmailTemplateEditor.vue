<script setup>
import { computed, watch, onBeforeUnmount, ref } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    modelValue: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    snippets: { type: Array, default: () => [] },
    variables: { type: Array, default: () => [] },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const dialog = useDialog();
const mode = ref('visual');

const editor = useEditor({
    content: props.modelValue || '',
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({ link: { openOnClick: false, autolink: true } }),
        Placeholder.configure({ placeholder: 'Soạn nội dung email… Dùng khối mẫu hoặc chèn biến bên dưới.' }),
    ],
    editorProps: {
        attributes: {
            class: 'tiptap email-template-editor min-h-[220px] px-3 py-2 text-sm leading-relaxed focus:outline-none',
        },
    },
    onUpdate: ({ editor: ed }) => {
        emit('update:modelValue', ed.isEmpty ? '' : ed.getHTML());
    },
});

watch(() => props.modelValue, (val) => {
    const ed = editor.value;
    if (!ed || mode.value !== 'visual') return;
    const html = val || '';
    if (html !== ed.getHTML() && !(ed.isEmpty && !html)) {
        ed.commands.setContent(html, false);
    }
});

watch(() => props.disabled, (off) => {
    editor.value?.setEditable(!off);
});

onBeforeUnmount(() => editor.value?.destroy());

const isActive = (name, attrs) => editor.value?.isActive(name, attrs) ?? false;
const run = (fn) => editor.value && fn(editor.value.chain().focus()).run();

function insertHtml(html) {
    if (props.disabled || !editor.value) return;
    editor.value.chain().focus().insertContent(html).run();
}

function insertVariable(key) {
    const token = `{{${key}}}`;
    if (mode.value === 'html' || !editor.value) {
        emit('update:modelValue', `${props.modelValue}${token}`);
        return;
    }
    insertHtml(token);
}

function insertSnippet(snippet) {
    if (mode.value === 'html' || !editor.value) {
        emit('update:modelValue', `${props.modelValue}${snippet.html}`);
        return;
    }
    insertHtml(snippet.html);
}

async function setLink() {
    const ed = editor.value;
    if (!ed || props.disabled) return;
    const prev = ed.getAttributes('link').href;
    const url = await dialog.prompt({
        title: 'Chèn liên kết',
        message: 'URL (có thể dùng {{task_url}}). Để trống để xoá link.',
        defaultValue: prev || '{{task_url}}',
        placeholder: 'https:// hoặc {{task_url}}',
        confirmText: 'Áp dụng',
    });
    if (url === null) return;
    if (url === '') {
        ed.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    ed.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

const toolbar = computed(() => [
    { key: 'bold', label: 'B', cls: 'font-bold', title: 'In đậm', active: isActive('bold'), on: () => run((c) => c.toggleBold()) },
    { key: 'italic', label: 'I', cls: 'italic', title: 'In nghiêng', active: isActive('italic'), on: () => run((c) => c.toggleItalic()) },
    { key: 'h3', label: 'H', cls: 'font-semibold', title: 'Tiêu đề', active: isActive('heading', { level: 3 }), on: () => run((c) => c.toggleHeading({ level: 3 })) },
    { key: 'bullet', label: '•', cls: '', title: 'Danh sách', active: isActive('bulletList'), on: () => run((c) => c.toggleBulletList()) },
    { key: 'link', label: '🔗', cls: '', title: 'Liên kết', active: isActive('link'), on: setLink },
]);

function onHtmlInput(e) {
    emit('update:modelValue', e.target.value);
}

function variableToken(key) {
    return `{{${key}}}`;
}

defineExpose({ insertVariable, insertSnippet });
</script>

<template>
  <div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div class="flex rounded-lg border border-slate-200 p-0.5">
        <button
          type="button"
          class="rounded-md px-2.5 py-1 text-xs font-medium transition"
          :class="mode === 'visual' ? 'bg-brand text-white' : 'text-slate-500'"
          @click="mode = 'visual'"
        >
          Soạn thảo
        </button>
        <button
          type="button"
          class="rounded-md px-2.5 py-1 text-xs font-medium transition"
          :class="mode === 'html' ? 'bg-brand text-white' : 'text-slate-500'"
          @click="mode = 'html'"
        >
          HTML
        </button>
      </div>
      <p class="text-[11px] text-slate-400">
        Khối mẫu giữ style email; chế độ HTML cho tùy chỉnh nâng cao.
      </p>
    </div>

    <div
      v-show="mode === 'visual'"
      class="overflow-hidden rounded-input border border-slate-200 focus-within:border-brand focus-within:ring-1 focus-within:ring-brand/30"
    >
      <div class="flex flex-wrap items-center gap-1 border-b border-slate-100 bg-slate-50 px-2 py-1.5">
        <button
          v-for="t in toolbar"
          :key="t.key"
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded px-1.5 text-sm transition disabled:opacity-40"
          :class="[t.cls, t.active ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200']"
          :title="t.title"
          :disabled="disabled"
          @click="t.on"
        >
          {{ t.label }}
        </button>
      </div>
      <EditorContent :editor="editor" />
    </div>

    <textarea
      v-show="mode === 'html'"
      :value="modelValue"
      rows="14"
      class="input w-full font-mono text-[11px] leading-relaxed"
      :disabled="disabled"
      @input="onHtmlInput"
    />

    <div class="grid gap-3 lg:grid-cols-2">
      <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-3">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Khối mẫu
        </p>
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="s in snippets"
            :key="s.id"
            type="button"
            class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 hover:border-brand/40 hover:text-brand disabled:opacity-40"
            :title="s.description"
            :disabled="disabled"
            @click="insertSnippet(s)"
          >
            + {{ s.label }}
          </button>
        </div>
      </div>
      <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-3">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Chèn biến
        </p>
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="v in variables"
            :key="v.key"
            type="button"
            class="rounded border border-slate-200 bg-white px-2 py-0.5 font-mono text-[10px] text-slate-600 hover:border-brand/40 hover:text-brand disabled:opacity-40"
            :title="v.hint"
            :disabled="disabled"
            @click="insertVariable(v.key)"
          >
            {{ variableToken(v.key) }}
          </button>
        </div>
      </div>
    </div>

    <p
      v-if="error"
      class="text-xs text-rose-600"
    >
      {{ error }}
    </p>
  </div>
</template>

<style>
.email-template-editor.tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    color: #94a3b8;
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
