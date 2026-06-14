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
const statsTick = ref(0);

const editor = useEditor({
    content: props.modelValue || '',
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({ link: { openOnClick: false, autolink: true } }),
        Placeholder.configure({ placeholder: 'Soạn nội dung email…' }),
    ],
    editorProps: {
        attributes: {
            class: 'tiptap email-template-editor min-h-[200px] px-3 py-2 text-sm leading-relaxed focus:outline-none',
        },
    },
    onUpdate: ({ editor: ed }) => {
        emit('update:modelValue', ed.isEmpty ? '' : ed.getHTML());
        statsTick.value += 1;
    },
    onSelectionUpdate: () => {
        statsTick.value += 1;
    },
});

watch(() => props.modelValue, (val) => {
    const ed = editor.value;
    if (!ed || mode.value !== 'visual') return;
    const html = val || '';
    if (html !== ed.getHTML() && !(ed.isEmpty && !html)) {
        ed.commands.setContent(html, false);
        statsTick.value += 1;
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
    statsTick.value += 1;
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
    { key: 'undo', label: '↶', cls: '', title: 'Hoàn tác', active: false, on: () => run((c) => c.undo()) },
    { key: 'redo', label: '↷', cls: '', title: 'Làm lại', active: false, on: () => run((c) => c.redo()) },
    { key: 'sep1', sep: true },
    { key: 'bold', label: 'B', cls: 'font-bold', title: 'In đậm', active: isActive('bold'), on: () => run((c) => c.toggleBold()) },
    { key: 'italic', label: 'I', cls: 'italic', title: 'In nghiêng', active: isActive('italic'), on: () => run((c) => c.toggleItalic()) },
    { key: 'underline', label: 'U', cls: 'underline', title: 'Gạch chân', active: isActive('underline'), on: () => run((c) => c.toggleUnderline()) },
    { key: 'h3', label: 'H', cls: 'font-semibold', title: 'Tiêu đề', active: isActive('heading', { level: 3 }), on: () => run((c) => c.toggleHeading({ level: 3 })) },
    { key: 'sep2', sep: true },
    { key: 'bullet', label: '•', cls: '', title: 'Danh sách', active: isActive('bulletList'), on: () => run((c) => c.toggleBulletList()) },
    { key: 'ordered', label: '1.', cls: '', title: 'Danh sách số', active: isActive('orderedList'), on: () => run((c) => c.toggleOrderedList()) },
    { key: 'quote', label: '❝', cls: '', title: 'Trích dẫn', active: isActive('blockquote'), on: () => run((c) => c.toggleBlockquote()) },
    { key: 'hr', label: '—', cls: '', title: 'Đường kẻ ngang', active: false, on: () => run((c) => c.setHorizontalRule()) },
    { key: 'link', label: '🔗', cls: '', title: 'Liên kết', active: isActive('link'), on: setLink },
]);

const contentStats = computed(() => {
    statsTick.value;
    if (mode.value === 'html') {
        const text = props.modelValue.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
        const words = text ? text.split(' ').filter(Boolean).length : 0;
        return { words, chars: text.length };
    }
    const ed = editor.value;
    if (!ed) return { words: 0, chars: 0 };
    const text = ed.getText().replace(/\s+/g, ' ').trim();
    const words = text ? text.split(' ').filter(Boolean).length : 0;
    return { words, chars: text.length };
});

function onHtmlInput(e) {
    emit('update:modelValue', e.target.value);
    statsTick.value += 1;
}

function variableToken(key) {
    return `{{${key}}}`;
}

const hasInsertRow = computed(() => props.snippets.length > 0 || props.variables.length > 0);
</script>

<template>
  <div class="space-y-2">
    <div class="flex flex-wrap items-center gap-2">
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
      <span class="text-[11px] text-slate-400 tabular-nums">
        {{ contentStats.words }} từ · {{ contentStats.chars }} ký tự
      </span>
    </div>

    <div
      v-show="mode === 'visual'"
      class="overflow-hidden rounded-input border border-slate-200 focus-within:border-brand focus-within:ring-1 focus-within:ring-brand/30"
    >
      <div class="flex flex-wrap items-center gap-1 border-b border-slate-100 bg-slate-50 px-2 py-1.5">
        <template
          v-for="t in toolbar"
          :key="t.key"
        >
          <span
            v-if="t.sep"
            class="mx-0.5 h-5 w-px bg-slate-200"
          />
          <button
            v-else
            type="button"
            class="grid h-7 min-w-7 place-items-center rounded px-1.5 text-sm transition disabled:opacity-40"
            :class="[t.cls, t.active ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200']"
            :title="t.title"
            :disabled="disabled"
            @click="t.on"
          >
            {{ t.label }}
          </button>
        </template>
      </div>
      <EditorContent :editor="editor" />
    </div>

    <textarea
      v-show="mode === 'html'"
      :value="modelValue"
      rows="12"
      class="input w-full font-mono text-[11px] leading-relaxed"
      :disabled="disabled"
      @input="onHtmlInput"
    />

    <div
      v-if="hasInsertRow"
      class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"
    >
      <template v-if="snippets.length">
        <span class="text-[11px] font-medium text-slate-500">Khối</span>
        <button
          v-for="s in snippets"
          :key="s.id"
          type="button"
          class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-600 hover:border-brand/40 hover:text-brand disabled:opacity-40"
          :disabled="disabled"
          @click="insertSnippet(s)"
        >
          {{ s.label }}
        </button>
      </template>
      <template v-if="variables.length">
        <span
          v-if="snippets.length"
          class="hidden h-4 w-px bg-slate-200 sm:block"
        />
        <span class="text-[11px] font-medium text-slate-500">Biến</span>
        <button
          v-for="v in variables"
          :key="v.key"
          type="button"
          class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-600 hover:border-brand/40 hover:text-brand disabled:opacity-40"
          :title="v.label"
          :disabled="disabled"
          @click="insertVariable(v.key)"
        >
          {{ variableToken(v.key) }}
        </button>
      </template>
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
