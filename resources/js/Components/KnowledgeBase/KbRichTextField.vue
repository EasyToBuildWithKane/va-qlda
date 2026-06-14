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
import { parseGoogleWorkspaceUrl } from '@/shared/googleWorkspaceUrl';
import { GoogleWorkspaceEmbed } from '@/shared/tiptap/GoogleWorkspaceEmbed';

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
    /** Tailwind min-height class for editor body */
    editorMinHeightClass: { type: String, default: 'min-h-[160px]' },
    /** Chèn preview Google Docs / Sheets (coaching session content) */
    enableGoogleWorkspaceEmbed: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function buildExtensions() {
    const ext = [
        StarterKit.configure({ link: { openOnClick: false, autolink: true } }),
        Placeholder.configure({ placeholder: props.placeholder }),
        Image.configure({ inline: true, allowBase64: false }),
    ];
    if (props.enableGoogleWorkspaceEmbed) {
        ext.push(GoogleWorkspaceEmbed);
    }
    return ext;
}

const editor = useEditor({
    content: props.modelValue || '',
    extensions: buildExtensions(),
    editorProps: {
        attributes: {
            class: `tiptap rich-content focus:outline-none ${props.editorMinHeightClass} px-3 py-2 text-sm`,
        },
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

async function insertGoogleWorkspace(preferType) {
    const isSheet = preferType === 'spreadsheet';
    const url = await dialog.prompt({
        title: isSheet ? 'Chèn Google Sheets' : 'Chèn Google Docs',
        message: isSheet
            ? 'Dán link Google Sheets. File cần quyền «Anyone with the link» để học viên xem preview.'
            : 'Dán link Google Docs. File cần quyền «Anyone with the link» để học viên xem preview.',
        defaultValue: isSheet
            ? 'https://docs.google.com/spreadsheets/d/'
            : 'https://docs.google.com/document/d/',
        confirmText: 'Chèn preview',
    });
    if (url === null || url === '') return;

    const parsed = parseGoogleWorkspaceUrl(url);
    if (!parsed) {
        toast.error('Link không hợp lệ. Chỉ hỗ trợ Google Docs hoặc Sheets trên docs.google.com.');
        return;
    }
    if (preferType && parsed.type !== preferType) {
        toast.error(isSheet ? 'Link không phải Google Sheets.' : 'Link không phải Google Docs.');
        return;
    }

    run((c) => c.insertContent({
        type: 'googleWorkspaceEmbed',
        attrs: {
            embedUrl: parsed.embed_url,
            viewUrl: parsed.view_url,
            workspaceType: parsed.type,
            label: parsed.default_title,
        },
    }));
}
</script>

<template>
  <div>
    <input
      v-if="imageUploadUrl"
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
          class="grid h-7 min-w-7 place-items-center rounded-input px-1.5 text-sm underline"
          :class="isActive('underline') ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200'"
          title="Gạch chân"
          @click="run((c) => c.toggleUnderline())"
        >
          U
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm line-through"
          :class="isActive('strike') ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200'"
          title="Gạch ngang"
          @click="run((c) => c.toggleStrike())"
        >
          S
        </button>
        <span class="mx-0.5 hidden h-5 w-px bg-slate-200 sm:inline-block" />
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Danh sách chấm"
          :class="isActive('bulletList') ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200'"
          @click="run((c) => c.toggleBulletList())"
        >
          •
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Danh sách số"
          :class="isActive('orderedList') ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200'"
          @click="run((c) => c.toggleOrderedList())"
        >
          1.
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Tiêu đề H2"
          :class="isActive('heading', { level: 2 }) ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200'"
          @click="run((c) => c.toggleHeading({ level: 2 }))"
        >
          H2
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Tiêu đề H3"
          :class="isActive('heading', { level: 3 }) ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200'"
          @click="run((c) => c.toggleHeading({ level: 3 }))"
        >
          H3
        </button>
        <button
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Liên kết"
          :class="isActive('link') ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-200'"
          @click="setLink"
        >
          🔗
        </button>
        <template v-if="enableGoogleWorkspaceEmbed">
          <span class="mx-0.5 hidden h-5 w-px bg-slate-200 sm:inline-block" />
          <button
            type="button"
            class="grid h-7 place-items-center rounded-input px-2 text-[11px] font-semibold text-slate-500 hover:bg-slate-200"
            title="Chèn Google Sheets (xem trước)"
            @click="insertGoogleWorkspace('spreadsheet')"
          >
            Sheet
          </button>
          <button
            type="button"
            class="grid h-7 place-items-center rounded-input px-2 text-[11px] font-semibold"
            title="Chèn Google Docs (xem trước)"
            @click="insertGoogleWorkspace('document')"
          >
            Docs
          </button>
        </template>
        <button
          v-if="imageUploadUrl"
          type="button"
          class="grid h-7 min-w-7 place-items-center rounded-input text-sm"
          title="Chèn ảnh"
          @click="pickImage"
        >
          🖼
        </button>
        <span class="mx-0.5 hidden h-5 w-px bg-slate-200 sm:inline-block" />
        <button
          type="button"
          class="grid h-7 w-7 place-items-center rounded-input text-sm text-slate-500 hover:bg-slate-200"
          title="Hoàn tác"
          @click="run((c) => c.undo())"
        >
          ↶
        </button>
        <button
          type="button"
          class="grid h-7 w-7 place-items-center rounded-input text-sm text-slate-500 hover:bg-slate-200"
          title="Làm lại"
          @click="run((c) => c.redo())"
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
