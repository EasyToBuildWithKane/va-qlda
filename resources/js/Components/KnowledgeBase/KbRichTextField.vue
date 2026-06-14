<script setup>
import { computed, watch, onBeforeUnmount, ref } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import Image from '@tiptap/extension-image';
import axios from 'axios';
import InfoTooltip from '@/Components/DailyReport/InfoTooltip.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';

const dialog = useDialog();
const toast = useToast();
const fileInput = ref(null);

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    tooltip: { type: String, default: '' },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    /** Route name ziggy: knowledge-base.articles.images.store */
    imageUploadUrl: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit.configure({ link: { openOnClick: false, autolink: true } }),
        Placeholder.configure({ placeholder: props.placeholder }),
        Image.configure({ inline: true, allowBase64: false }),
    ],
    editorProps: {
        attributes: { class: 'tiptap rich-content focus:outline-none min-h-[160px] px-3 py-2 text-sm' },
    },
    onUpdate: ({ editor: ed }) => {
        emit('update:modelValue', ed.isEmpty ? '' : ed.getHTML());
    },
});

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

async function uploadImage(file) {
    if (!props.imageUploadUrl) {
        toast.error('Lưu bài trước khi chèn ảnh.');
        return;
    }
    const form = new FormData();
    form.append('image', file);
    try {
        const { data } = await axios.post(props.imageUploadUrl, form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (data?.url) {
            run((c) => c.setImage({ src: data.url, alt: file.name }));
        }
    } catch {
        toast.error('Không tải được ảnh. Vui lòng thử lại.');
    }
}

function pickImage() {
    if (!props.imageUploadUrl) {
        toast.error('Lưu bài trước khi chèn ảnh.');
        return;
    }
    fileInput.value?.click();
}

function onFileChange(e) {
    const file = e.target.files?.[0];
    if (file) uploadImage(file);
    e.target.value = '';
}

const setLink = async () => {
    const ed = editor.value;
    if (!ed) return;
    const prev = ed.getAttributes('link').href;
    const url = await dialog.prompt({
        title: 'Chèn liên kết',
        message: 'Nhập URL. Để trống để xoá liên kết.',
        defaultValue: prev || 'https://',
        confirmText: 'Áp dụng',
    });
    if (url === null) return;
    if (url === '') {
        ed.chain().focus().extendMarkRange('link').unsetLink().run();
        return;
    }
    ed.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};
</script>

<template>
  <div>
    <input
      ref="fileInput"
      type="file"
      accept="image/jpeg,image/png,image/gif,image/webp"
      class="hidden"
      @change="onFileChange"
    >
    <div
      v-if="label"
      class="mb-1 flex items-center justify-between"
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
    <div class="overflow-hidden rounded-input border border-slate-300 focus-within:border-brand focus-within:ring-1 focus-within:ring-brand/30">
      <div class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-1.5">
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input px-1.5 text-sm font-bold"
          :class="isActive('bold') ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200'"
          @click="run((c) => c.toggleBold())"
        >
          B
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input px-1.5 text-sm italic"
          :class="isActive('italic') ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200'"
          @click="run((c) => c.toggleItalic())"
        >
          I
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Danh sách"
          @click="run((c) => c.toggleBulletList())"
        >
          •
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Tiêu đề H2"
          @click="run((c) => c.toggleHeading({ level: 2 }))"
        >
          H2
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Tiêu đề H3"
          @click="run((c) => c.toggleHeading({ level: 3 }))"
        >
          H3
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Liên kết"
          @click="setLink"
        >
          🔗
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Chèn ảnh"
          @click="pickImage"
        >
          🖼
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
