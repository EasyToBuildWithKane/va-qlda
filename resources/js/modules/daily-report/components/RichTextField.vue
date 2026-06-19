<script setup>
import { computed, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import InfoTooltip from './InfoTooltip.vue';
import { useDialog } from '@/composables/useDialog';

const dialog = useDialog();

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    tooltip: { type: String, default: '' },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit.configure({ link: { openOnClick: false, autolink: true } }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: { class: 'tiptap rich-content focus:outline-none min-h-[120px] px-3 py-2 text-sm' },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.isEmpty ? '' : editor.getHTML());
    },
});

// Sync external changes (e.g. applying a template) into the editor.
watch(() => props.modelValue, (val) => {
    const ed = editor.value;
    if (!ed) return;
    if ((val || '') !== ed.getHTML() && !(ed.isEmpty && !val)) {
        ed.commands.setContent(val || '', false);
    }
});

onBeforeUnmount(() => editor.value?.destroy());

const plainText = computed(() => (editor.value ? editor.value.getText().trim() : ''));
const filled = computed(() => plainText.value.length > 0);

const isActive = (name, attrs) => editor.value?.isActive(name, attrs) ?? false;

const run = (fn) => editor.value && fn(editor.value.chain().focus()).run();
const toggleBold = () => run((c) => c.toggleBold());
const toggleItalic = () => run((c) => c.toggleItalic());
const toggleUnderline = () => run((c) => c.toggleUnderline());
const toggleBullet = () => run((c) => c.toggleBulletList());
const toggleOrdered = () => run((c) => c.toggleOrderedList());
const undo = () => run((c) => c.undo());
const redo = () => run((c) => c.redo());

const setLink = async () => {
    const ed = editor.value;
    if (!ed) return;
    const prev = ed.getAttributes('link').href;
    const url = await dialog.prompt({
        title: 'Chèn liên kết',
        message: 'Nhập đường dẫn (URL). Để trống để xoá liên kết.',
        defaultValue: prev || 'https://',
        placeholder: 'https://',
        confirmText: 'Áp dụng',
    });
    if (url === null) return;
    if (url === '') {
        ed.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    ed.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

const tools = computed(() => [
    { key: 'bold', label: 'B', title: 'In đậm', on: toggleBold, active: isActive('bold'), cls: 'font-bold' },
    { key: 'italic', label: 'I', title: 'In nghiêng', on: toggleItalic, active: isActive('italic'), cls: 'italic' },
    { key: 'underline', label: 'U', title: 'Gạch chân', on: toggleUnderline, active: isActive('underline'), cls: 'underline' },
    { key: 'bullet', label: '•', title: 'Danh sách', on: toggleBullet, active: isActive('bulletList') },
    { key: 'ordered', label: '1.', title: 'Danh sách số', on: toggleOrdered, active: isActive('orderedList') },
    { key: 'link', label: '🔗', title: 'Chèn liên kết', on: setLink, active: isActive('link') },
]);
</script>

<template>
  <div>
    <div
      v-if="label"
      class="flex items-center justify-between mb-1"
    >
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
      <span class="text-xs text-slate-400">{{ plainText.length }} ký tự</span>
    </div>

    <p
      v-if="hint"
      class="mb-1.5 text-xs text-slate-400"
    >
      {{ hint }}
    </p>

    <div class="rounded-input border border-slate-300 overflow-hidden focus-within:border-brand focus-within:ring-1 focus-within:ring-brand/30">
      <!-- Toolbar -->
      <div class="flex items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5">
        <button
          v-for="t in tools"
          :key="t.key"
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input px-1.5 text-sm transition"
          :class="[t.cls, t.active ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200']"
          :title="t.title"
          @click="t.on"
        >
          {{ t.label }}
        </button>

        <span class="mx-1 h-5 w-px bg-slate-200" />
        <button
          type="button"
          class="grid h-7 w-7 place-items-center rounded-input text-slate-500 transition hover:bg-slate-200"
          title="Hoàn tác"
          @click="undo"
        >
          ↶
        </button>
        <button
          type="button"
          class="grid h-7 w-7 place-items-center rounded-input text-slate-500 transition hover:bg-slate-200"
          title="Làm lại"
          @click="redo"
        >
          ↷
        </button>
      </div>

      <EditorContent :editor="editor" />
    </div>

    <p
      v-if="error"
      class="mt-1 text-sm text-danger"
    >
      {{ error }}
    </p>
  </div>
</template>

<style>
/* Tiptap placeholder (Placeholder extension marks the empty first paragraph). */
.tiptap p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    color: #94a3b8;
    float: left;
    height: 0;
    pointer-events: none;
}
.tiptap:focus {
    outline: none;
}
</style>
