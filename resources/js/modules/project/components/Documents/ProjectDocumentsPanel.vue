<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { useToast } from '@/shared/composables/useToast';
import Modal from '@/Components/Ui/Modal.vue';
import DocumentPreviewPane from '@/modules/project/components/Documents/DocumentPreviewPane.vue';
import ProjectDocumentDetailAside from '@/modules/project/components/Documents/ProjectDocumentDetailAside.vue';
import Drawer from '@/Components/Ui/Drawer.vue';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta } from '@/composables/useModalDraftHelpers';

const props = defineProps({
    projectId: { type: [Number, String], required: true },
    attachments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    canUpload: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const toast = useToast();
const confirmDelete = useConfirmDelete();
const activeCategory = ref(props.categories[0]?.value ?? 'customer');
const selectedId = ref(null);
const uploadingCategory = ref(null);
const dragging = ref(false);
const fileInputs = ref({});
const replaceInput = ref(null);
const editingNotes = ref(false);
const showLinkModal = ref(false);
const editingLink = ref(false);
const linkModalRef = ref(null);
const detailDrawerOpen = ref(false);

const linkForm = useForm({
    category: '',
    title: '',
    external_url: '',
});

const linkDraft = useModalFormDraft('project-attachment-link', {
    getScope: () => `${props.projectId}.${activeCategory.value}`,
    fields: ['category', 'title', 'external_url'],
});

const saveLinkDraftOnClose = () => {
    linkDraft.saveOnClose({
        category: linkForm.category,
        title: linkForm.title,
        external_url: linkForm.external_url,
    }, buildDraftSaveMeta(null));
};

const colorBadge = {
    sky: 'bg-sky-100 text-sky-700',
    violet: 'bg-violet-100 text-violet-700',
    amber: 'bg-amber-100 text-amber-700',
    emerald: 'bg-emerald-100 text-emerald-700',
    rose: 'bg-rose-100 text-rose-700',
};

const colorTab = {
    sky: 'border-sky-500 text-sky-700',
    violet: 'border-violet-500 text-violet-700',
    amber: 'border-amber-500 text-amber-700',
    emerald: 'border-emerald-500 text-emerald-700',
    rose: 'border-rose-500 text-rose-700',
};

const byCategory = computed(() => {
    const map = {};
    props.categories.forEach((c) => { map[c.value] = []; });
    props.attachments.forEach((f) => {
        if (map[f.category]) map[f.category].push(f);
    });
    return map;
});

const activeCat = computed(() => props.categories.find((c) => c.value === activeCategory.value));
const categoryFiles = computed(() => byCategory.value[activeCategory.value] || []);

const selected = computed(() => {
    if (!selectedId.value) return null;
    return props.attachments.find((f) => f.id === selectedId.value) ?? null;
});

const notesForm = useForm({ notes: '' });

const firstAvailableFile = (files) => files.find((f) => f.url) ?? null;

watch(categoryFiles, (files) => {
    if (!files.length) {
        selectedId.value = null;
        return;
    }
    const current = files.find((f) => f.id === selectedId.value);
    if (!current?.url) {
        selectedId.value = firstAvailableFile(files)?.id ?? null;
    }
}, { immediate: true });

watch(selected, (file) => {
    notesForm.notes = file?.notes ?? '';
    editingNotes.value = false;
    editingLink.value = false;
    notesForm.clearErrors();
    linkForm.title = file?.original_name ?? '';
    linkForm.external_url = file?.url ?? '';
    linkForm.clearErrors();
}, { immediate: true });

watch(activeCategory, () => {
    const files = byCategory.value[activeCategory.value] || [];
    selectedId.value = files[0]?.id ?? null;
});

const totalCount = computed(() => props.attachments.length);

const workspaceGridClass = computed(() => (
    selected.value
        ? 'lg:grid-cols-[minmax(200px,228px)_minmax(0,1fr)_minmax(240px,272px)]'
        : 'lg:grid-cols-[minmax(200px,228px)_minmax(0,1fr)]'
));

const cancelNotesEdit = () => {
    editingNotes.value = false;
    notesForm.notes = selected.value?.notes ?? '';
};

const cancelLinkEdit = () => {
    editingLink.value = false;
    linkForm.title = selected.value?.original_name ?? '';
    linkForm.external_url = selected.value?.url ?? '';
};

const listBadge = (file) => {
    if (file.is_google_doc || file.preview_kind === 'google_doc') return 'DOC';
    if (file.is_google_sheet || file.preview_kind === 'google_sheet') return 'SHT';
    return fileExt(file.original_name);
};

const formatSize = (bytes, file = null) => {
    if (file?.is_external_link) return 'Link Google';
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let n = bytes;
    let i = 0;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return `${n.toFixed(i ? 1 : 0)} ${units[i]}`;
};

const acceptFor = (category) => {
    if (category === 'images') return 'image/*,.pdf,.png,.jpg,.jpeg,.gif,.webp,.svg';
    if (category === 'customer_data') return '.xls,.xlsx,.csv,.json,.txt,.zip';
    return 'image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt,.csv';
};

const setFileInput = (category, el) => { if (el) fileInputs.value[category] = el; };
const pickFiles = (category) => fileInputs.value[category]?.click();

const uploadFiles = (category, fileList, inputEl = null) => {
    const files = [...(fileList || [])];
    if (!files.length || !props.canUpload) return;
    uploadingCategory.value = category;
    router.post(`/projects/${props.projectId}/attachments`, { category, files }, {
        forceFormData: true,
        preserveScroll: true,
        onError: () => toast.error('Không thể tải lên. Kiểm tra định dạng và dung lượng file.'),
        onFinish: () => {
            uploadingCategory.value = null;
            if (inputEl) inputEl.value = '';
        },
    });
};

const onFilesSelected = (category, event) => {
    uploadFiles(category, event.target.files, event.target);
};

const onDragOver = () => {
    if (props.canUpload) dragging.value = true;
};

const onDrop = (category, event) => {
    dragging.value = false;
    uploadFiles(category, event.dataTransfer?.files);
};

const openAddLinkModal = async () => {
    linkForm.reset();
    linkForm.category = activeCategory.value;
    linkForm.clearErrors();
    showLinkModal.value = true;
    const epoch = linkDraft.bumpOpenEpoch();
    await linkDraft.tryRestore((data) => {
        linkForm.category = data.category ?? activeCategory.value;
        linkForm.title = data.title ?? '';
        linkForm.external_url = data.external_url ?? '';
    }, {
        isActive: () => showLinkModal.value,
        openEpoch: epoch,
    });
};

const closeLinkModal = () => {
    showLinkModal.value = false;
    linkForm.reset();
    linkForm.clearErrors();
};

const requestCloseLinkModal = () => {
    linkModalRef.value?.tryClose?.();
};

const submitLink = () => {
    if (!props.canUpload) return;
    linkForm.post(`/projects/${props.projectId}/attachments`, {
        preserveScroll: true,
        onSuccess: () => {
            linkDraft.clear();
            closeLinkModal();
        },
        onError: () => toast.error('Không thể thêm link. Kiểm tra URL Google Docs hoặc Sheets.'),
    });
};

const saveLink = () => {
    if (!selected.value?.is_external_link || !props.canEdit) return;
    linkForm.put(`/projects/${props.projectId}/attachments/${selected.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingLink.value = false;
        },
        onError: () => toast.error('Link không hợp lệ hoặc không thể lưu.'),
    });
};

const selectFile = (file) => { selectedId.value = file.id; };

const removeFile = (file) => {
    confirmDelete(
        `Xoá "${file.original_name}"?`,
        () => router.delete(`/projects/${props.projectId}/attachments/${file.id}`, {
            preserveScroll: true,
        }),
        { title: 'Xoá tài liệu' },
    );
};

const saveNotes = () => {
    if (!selected.value) return;
    notesForm.put(`/projects/${props.projectId}/attachments/${selected.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingNotes.value = false;
        },
    });
};

const pickReplace = () => replaceInput.value?.click();

const onReplaceSelected = (event) => {
    const file = event.target.files?.[0];
    if (!file || !selected.value) return;
    const form = new FormData();
    form.append('file', file);
    form.append('_method', 'PUT');
    router.post(`/projects/${props.projectId}/attachments/${selected.value.id}`, form, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => { event.target.value = ''; },
    });
};

const activities = computed(() => {
    const list = selected.value?.activities;
    if (Array.isArray(list)) return list;
    if (list?.data && Array.isArray(list.data)) return list.data;
    return [];
});

const activityIcon = (event) => ({
    uploaded: 'upload',
    link_added: 'link',
    link_updated: 'edit',
    note_updated: 'edit',
    replaced: 'refresh',
    deleted: 'delete',
}[event] || 'report-history');

const fileExt = (name) => {
    const parts = (name || '').split('.');
    return parts.length > 1 ? parts.pop().toUpperCase() : 'FILE';
};

const formatFileType = (file) => {
    const name = (file?.original_name || '').toLowerCase();
    const ext = name.includes('.') ? name.split('.').pop() : '';
    const map = {
        pdf: 'PDF',
        docx: 'Word (DOCX)',
        doc: 'Word (DOC)',
        xlsx: 'Excel (XLSX)',
        xls: 'Excel (XLS)',
        pptx: 'PowerPoint (PPTX)',
        ppt: 'PowerPoint (PPT)',
        png: 'Ảnh PNG',
        jpg: 'Ảnh JPEG',
        jpeg: 'Ảnh JPEG',
        gif: 'Ảnh GIF',
        webp: 'Ảnh WebP',
        zip: 'Nén ZIP',
        csv: 'CSV',
        txt: 'Văn bản',
    };
    if (ext && map[ext]) return map[ext];
    if (file?.is_image) return 'Hình ảnh';
    if (file?.is_google_doc) return 'Google Docs';
    if (file?.is_google_sheet) return 'Google Sheets';
    if (file?.is_pdf) return 'PDF';
    if (file?.mime_type) {
        const short = file.mime_type.split('/').pop();
        return short.length <= 12 ? short.toUpperCase() : ext.toUpperCase() || 'Tài liệu';
    }
    return ext.toUpperCase() || '—';
};

const activityTone = (event) => ({
    uploaded: 'bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-400',
    link_added: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400',
    link_updated: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400',
    note_updated: 'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400',
    replaced: 'bg-violet-100 text-violet-600 dark:bg-violet-950 dark:text-violet-400',
    deleted: 'bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400',
}[event] || 'bg-slate-100 text-slate-500 dark:bg-slate-800');
</script>

<template>
  <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-white dark:bg-slate-900">
    <!-- Header gọn -->
    <div class="shrink-0 border-b border-slate-200 bg-slate-50/80 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
      <div class="flex items-center justify-between gap-2">
        <h2 class="font-display text-sm font-semibold text-slate-800 dark:text-slate-100">
          Tài liệu dự án
        </h2>
        <span class="text-xs text-slate-500">
          Tổng <span class="font-semibold text-brand">{{ totalCount }}</span>
        </span>
      </div>
      <nav class="mt-1.5 flex gap-0.5 overflow-x-auto">
        <button
          v-for="cat in categories"
          :key="cat.value"
          type="button"
          class="flex shrink-0 items-center gap-1 border-b-2 px-2 py-1.5 text-xs font-medium transition sm:px-2.5 sm:text-sm"
          :class="activeCategory === cat.value
            ? (colorTab[cat.color] || 'border-brand text-brand')
            : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="activeCategory = cat.value"
        >
          <AppIcon
            :name="cat.icon"
            :size="14"
          />
          {{ cat.label }}
          <span
            class="inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-bold"
            :class="(byCategory[cat.value] || []).length
              ? (colorBadge[cat.color] || 'bg-brand/10 text-brand')
              : 'bg-slate-100 text-slate-400'"
          >{{ (byCategory[cat.value] || []).length }}</span>
        </button>
      </nav>
    </div>

    <div
      class="shrink-0 border-b border-slate-100 bg-white px-3 py-1.5 dark:border-slate-700 dark:bg-slate-900"
      :class="canUpload && !categoryFiles.length ? 'space-y-1.5' : ''"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p
          class="min-w-0 flex-1 truncate text-[11px] text-slate-500 dark:text-slate-400"
          :title="activeCat?.description"
        >
          <span class="font-medium text-slate-600 dark:text-slate-300">{{ activeCat?.label }}:</span>
          {{ activeCat?.description }}
        </p>
        <div
          v-if="canUpload"
          class="flex flex-wrap items-center gap-2"
        >
          <button
            type="button"
            class="btn-ghost inline-flex items-center gap-1.5 border border-slate-200 text-xs dark:border-slate-600"
            @click="openAddLinkModal"
          >
            <AppIcon
              name="link"
              :size="14"
            />
            Thêm link Google
          </button>
          <button
            type="button"
            class="btn-ghost inline-flex items-center gap-1.5 border border-slate-200 text-xs dark:border-slate-600"
            :disabled="uploadingCategory === activeCategory"
            @click="pickFiles(activeCategory)"
          >
            <AppIcon
              :name="uploadingCategory === activeCategory ? 'refresh' : 'upload'"
              :size="14"
              :class="uploadingCategory === activeCategory ? 'animate-spin' : ''"
            />
            {{ uploadingCategory === activeCategory ? 'Đang tải…' : 'Chọn file' }}
          </button>
        </div>
        <input
          :ref="(el) => setFileInput(activeCategory, el)"
          type="file"
          class="hidden"
          multiple
          :accept="acceptFor(activeCategory)"
          @change="onFilesSelected(activeCategory, $event)"
        >
      </div>
      <div
        v-if="canUpload && !categoryFiles.length"
        class="rounded-lg border-2 border-dashed px-4 py-2.5 text-center transition"
        :class="dragging
          ? 'border-brand bg-brand/5'
          : 'border-slate-200 bg-slate-50/80 dark:border-slate-600 dark:bg-slate-800/40'"
        @dragover.prevent="dragging = true"
        @dragleave="dragging = false"
        @drop.prevent="onDrop(activeCategory, $event)"
      >
        <p class="text-xs font-medium text-slate-600 dark:text-slate-300">
          Kéo thả file vào đây · PDF, Office, ảnh, ZIP… · tối đa 20MB/file
        </p>
      </div>
    </div>

    <!-- Danh sách | Preview full-height | Chi tiết (sidebar) -->
    <div
      class="grid min-h-0 flex-1 overflow-hidden max-lg:grid-rows-[minmax(0,26vh)_minmax(0,1fr)] lg:grid-rows-1"
      :class="workspaceGridClass"
    >
      <div class="flex min-h-0 flex-col overflow-hidden border-b border-slate-200 bg-slate-50/50 lg:border-b-0 lg:border-r dark:border-slate-700 dark:bg-slate-900/50">
        <div class="shrink-0 border-b border-slate-200/80 bg-white px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
          Danh sách ({{ categoryFiles.length }})
        </div>
        <div
          class="min-h-0 flex-1 overflow-y-auto bg-white dark:bg-slate-900"
          :class="canUpload && dragging && categoryFiles.length ? 'ring-2 ring-inset ring-brand/30' : ''"
          @dragover.prevent="onDragOver"
          @dragleave="dragging = false"
          @drop.prevent="onDrop(activeCategory, $event)"
        >
          <div
            v-if="!categoryFiles.length"
            class="flex flex-col items-center justify-center px-4 py-16 text-center"
            :class="canUpload && dragging ? 'bg-brand/5' : ''"
          >
            <AppIcon
              :name="activeCat?.icon || 'documents'"
              :size="32"
              class="text-slate-300"
            />
            <p class="mt-2 text-sm text-slate-400">
              {{ canUpload ? 'Chưa có tài liệu — kéo thả hoặc chọn file.' : 'Chưa có tài liệu.' }}
            </p>
            <button
              v-if="canUpload"
              type="button"
              class="mt-2 text-sm font-medium text-brand hover:underline"
              @click="pickFiles(activeCategory)"
            >
              Tải file đầu tiên
            </button>
          </div>
          <ul
            v-else
            class="divide-y divide-slate-100 dark:divide-slate-800"
          >
            <li
              v-for="file in categoryFiles"
              :key="file.id"
            >
              <button
                type="button"
                class="flex w-full items-start gap-2.5 px-3 py-2.5 text-left transition"
                :class="selectedId === file.id
                  ? 'bg-brand/8 ring-1 ring-inset ring-brand/20'
                  : 'hover:bg-slate-50 dark:hover:bg-slate-800'"
                @click="selectFile(file)"
              >
                <span
                  class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-[10px] font-bold uppercase"
                  :class="file.is_image
                    ? 'bg-rose-50 text-rose-600'
                    : (file.is_google_doc || file.is_google_sheet)
                      ? 'bg-emerald-50 text-emerald-700'
                      : 'bg-slate-100 text-slate-500'"
                >
                  <img
                    v-if="file.is_image"
                    :src="file.url"
                    :alt="file.original_name"
                    class="h-9 w-9 rounded-lg object-cover"
                  >
                  <span v-else>{{ listBadge(file) }}</span>
                </span>
                <span class="min-w-0 flex-1">
                  <span class="line-clamp-2 text-sm font-medium text-slate-800 dark:text-slate-100">{{ file.original_name }}</span>
                  <span class="mt-0.5 block text-[10px] text-slate-400">
                    {{ formatSize(file.size, file) }}
                    <span v-if="file.uploaded_by?.name"> · {{ file.uploaded_by.name.split(' ').pop() }}</span>
                  </span>
                </span>
              </button>
            </li>
          </ul>
        </div>
      </div>

      <!-- Preview — full chiều cao còn lại, không cuộn -->
      <div class="flex min-h-0 flex-col overflow-hidden bg-slate-100/80 dark:bg-slate-950 lg:border-l lg:border-slate-200/80 dark:lg:border-slate-800">
        <template v-if="selected">
          <div class="flex shrink-0 items-center gap-2 border-b border-slate-200/80 bg-white px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900">
            <h3 class="min-w-0 flex-1 truncate text-sm font-medium text-slate-800 dark:text-slate-100">
              {{ selected.original_name }}
            </h3>
            <button
              type="button"
              class="btn-ghost inline-flex shrink-0 items-center gap-1 px-2 py-1 text-[11px] lg:hidden"
              @click="detailDrawerOpen = true"
            >
              <AppIcon
                name="info"
                :size="14"
              />
              Chi tiết
            </button>
            <div class="flex shrink-0 items-center gap-0.5">
              <a
                :href="selected.url"
                target="_blank"
                rel="noopener noreferrer"
                class="btn-ghost grid h-7 w-7 place-items-center p-0"
                title="Mở tab mới"
              >
                <AppIcon
                  name="eye"
                  :size="14"
                />
              </a>
              <a
                v-if="!selected.is_external_link"
                :href="selected.url"
                download
                class="btn-ghost grid h-7 w-7 place-items-center p-0"
                title="Tải xuống"
              >
                <AppIcon
                  name="download"
                  :size="14"
                />
              </a>
              <button
                v-if="canDelete"
                type="button"
                class="btn-ghost grid h-7 w-7 place-items-center p-0 text-rose-500"
                title="Xoá"
                @click="removeFile(selected)"
              >
                <AppIcon
                  name="delete"
                  :size="14"
                />
              </button>
            </div>
          </div>
          <div class="min-h-0 flex-1 overflow-hidden p-1.5">
            <DocumentPreviewPane :file="selected" />
          </div>
        </template>
        <div
          v-else
          class="flex min-h-0 flex-1 flex-col items-center justify-center p-6 text-center text-slate-400"
        >
          <AppIcon
            name="documents"
            :size="32"
            class="opacity-40"
          />
          <p class="mt-2 text-sm">
            Chọn tài liệu để xem trước.
          </p>
        </div>
      </div>

      <!-- Chi tiết file — cột phải (desktop) -->
      <aside
        v-if="selected"
        class="hidden min-h-0 flex-col overflow-y-auto border-l border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 lg:flex"
      >
        <ProjectDocumentDetailAside
          :selected="selected"
          :activities="activities"
          :can-edit="canEdit"
          :can-delete="canDelete"
          :notes-form="notesForm"
          :editing-notes="editingNotes"
          :editing-link="editingLink"
          :link-form="linkForm"
          :format-size="formatSize"
          :format-file-type="formatFileType"
          :activity-icon="activityIcon"
          :activity-tone="activityTone"
          @edit-notes="editingNotes = true"
          @cancel-notes="cancelNotesEdit"
          @save-notes="saveNotes"
          @edit-link="editingLink = true"
          @cancel-link="cancelLinkEdit"
          @save-link="saveLink"
          @replace="pickReplace"
          @update:notes="notesForm.notes = $event"
          @update:link-title="linkForm.title = $event"
          @update:link-url="linkForm.external_url = $event"
        />
      </aside>
    </div>

    <input
      ref="replaceInput"
      type="file"
      class="hidden"
      :accept="acceptFor(activeCategory)"
      @change="onReplaceSelected"
    >

    <Drawer
      :show="Boolean(selected && detailDrawerOpen)"
      title="Chi tiết tài liệu"
      width="max-w-md"
      flush
      @close="detailDrawerOpen = false"
    >
      <ProjectDocumentDetailAside
        v-if="selected"
        :selected="selected"
        :activities="activities"
        :can-edit="canEdit"
        :can-delete="canDelete"
        :notes-form="notesForm"
        :editing-notes="editingNotes"
        :editing-link="editingLink"
        :link-form="linkForm"
        :format-size="formatSize"
        :format-file-type="formatFileType"
        :activity-icon="activityIcon"
        :activity-tone="activityTone"
        @edit-notes="editingNotes = true"
        @cancel-notes="cancelNotesEdit"
        @save-notes="saveNotes"
        @edit-link="editingLink = true"
        @cancel-link="cancelLinkEdit"
        @save-link="saveLink"
        @replace="pickReplace"
        @update:notes="notesForm.notes = $event"
        @update:link-title="linkForm.title = $event"
        @update:link-url="linkForm.external_url = $event"
      />
    </Drawer>

    <Modal
      ref="linkModalRef"
      :show="showLinkModal"
      title="Thêm link Google Docs / Sheets"
      max-width="max-w-md"
      :dirty="Boolean(linkForm.title || linkForm.external_url)"
      :on-save-draft="saveLinkDraftOnClose"
      @close="closeLinkModal"
    >
      <p class="mb-4 text-sm text-slate-500">
        Dán link chia sẻ từ Google (quyền «Bất kỳ ai có link» hoặc tài khoản VA có quyền xem) vào danh mục
        <span class="font-medium text-slate-700">«{{ activeCat?.label }}»</span>.
      </p>
      <div class="space-y-3">
        <div>
          <label
            for="doc-link-title"
            class="text-xs font-medium text-slate-500"
          >Tên hiển thị (tuỳ chọn)</label>
          <input
            id="doc-link-title"
            v-model="linkForm.title"
            type="text"
            class="input mt-1 w-full text-sm"
            placeholder="VD: Đặc tả v1.2"
          >
        </div>
        <div>
          <label
            for="doc-link-url"
            class="text-xs font-medium text-slate-500"
          >Link Google</label>
          <input
            id="doc-link-url"
            v-model="linkForm.external_url"
            type="url"
            class="input mt-1 w-full text-sm"
            placeholder="https://docs.google.com/document/d/…"
            required
          >
          <p
            v-if="linkForm.errors.external_url"
            class="mt-1 text-xs text-rose-600"
          >
            {{ linkForm.errors.external_url }}
          </p>
          <p
            v-else-if="linkForm.errors.files"
            class="mt-1 text-xs text-rose-600"
          >
            {{ linkForm.errors.files }}
          </p>
        </div>
      </div>
      <div class="mt-5 flex justify-end gap-2">
        <button
          type="button"
          class="btn-ghost text-sm"
          @click="requestCloseLinkModal"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary text-sm"
          :disabled="linkForm.processing || !linkForm.external_url.trim()"
          @click="submitLink"
        >
          {{ linkForm.processing ? 'Đang lưu…' : 'Thêm link' }}
        </button>
      </div>
    </Modal>
  </div>
</template>

