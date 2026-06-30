<script setup>
import { ref, computed, watch, reactive, onMounted } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { useToast } from '@/shared/composables/useToast';
import Modal from '@/Components/Ui/Modal.vue';
import DocumentPreviewPane from '@/modules/project/components/Documents/DocumentPreviewPane.vue';
import ProjectDocumentDetailAside from '@/modules/project/components/Documents/ProjectDocumentDetailAside.vue';
import ProjectDocumentTree from '@/modules/project/components/Documents/ProjectDocumentTree.vue';
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
const activeFolderId = ref(null);
const expandedIds = reactive({});
const showFolderModal = ref(false);
const folderModalRef = ref(null);

const folderForm = useForm({
    category: '',
    folder_name: '',
    parent_id: null,
    is_folder: true,
});

const expandedStorageKey = computed(() => `va-project-doc-tree:${props.projectId}:${activeCategory.value}`);

const loadExpandedState = () => {
    Object.keys(expandedIds).forEach((k) => { delete expandedIds[k]; });
    try {
        const raw = localStorage.getItem(expandedStorageKey.value);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
            Object.assign(expandedIds, parsed);
        }
    } catch {
        /* ignore */
    }
};

const persistExpandedState = () => {
    try {
        localStorage.setItem(expandedStorageKey.value, JSON.stringify({ ...expandedIds }));
    } catch {
        /* ignore */
    }
};

onMounted(loadExpandedState);

const linkForm = useForm({
    category: '',
    title: '',
    external_url: '',
    parent_id: null,
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

const sectionLabelClass = 'text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';

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

const countFolderContents = (folderId, items) => {
    const childrenByParent = new Map();
    items.forEach((item) => {
        const pid = item.parent_id ?? null;
        if (!childrenByParent.has(pid)) childrenByParent.set(pid, []);
        childrenByParent.get(pid).push(item);
    });
    let subfolders = 0;
    let files = 0;
    const walk = (id) => {
        (childrenByParent.get(id) || []).forEach((ch) => {
            if (ch.is_folder) {
                subfolders += 1;
                walk(ch.id);
            } else {
                files += 1;
            }
        });
    };
    walk(folderId);
    return { subfolders, files };
};

const buildDocumentTree = (items, parentId = null) => items
    .filter((item) => (item.parent_id ?? null) === parentId)
    .sort((a, b) => {
        if (Boolean(a.is_folder) !== Boolean(b.is_folder)) {
            return a.is_folder ? -1 : 1;
        }
        return (a.original_name || '').localeCompare(b.original_name || '', 'vi');
    })
    .map((item) => {
        const children = item.is_folder ? buildDocumentTree(items, item.id) : [];
        const stats = item.is_folder ? countFolderContents(item.id, items) : { subfolders: 0, files: 0 };
        return {
            ...item,
            children,
            file_count: stats.files,
            subfolder_count: stats.subfolders,
        };
    });

const documentTree = computed(() => buildDocumentTree(categoryFiles.value));

const categoryItemCount = (value) => (byCategory.value[value] || []).filter((f) => !f.is_folder).length;

const folderPath = computed(() => {
    if (!activeFolderId.value) return [];
    const path = [];
    let id = activeFolderId.value;
    while (id) {
        const node = props.attachments.find((a) => a.id === id && a.category === activeCategory.value);
        if (!node) break;
        path.unshift(node);
        id = node.parent_id ?? null;
    }
    return path;
});

const activeFolderLabel = computed(() => {
    if (!activeFolderId.value) return 'Gốc danh mục';
    return folderPath.value[folderPath.value.length - 1]?.original_name ?? 'Thư mục';
});

const selected = computed(() => {
    if (!selectedId.value) return null;
    const file = props.attachments.find((f) => f.id === selectedId.value) ?? null;
    if (file?.is_folder) return null;
    return file;
});

const notesForm = useForm({ notes: '' });

const firstAvailableFile = (files) => files.find((f) => f.url) ?? null;

watch(categoryFiles, (files) => {
    const docs = files.filter((f) => !f.is_folder);
    if (!docs.length) {
        selectedId.value = null;
        return;
    }
    const current = docs.find((f) => f.id === selectedId.value);
    if (!current?.url) {
        selectedId.value = firstAvailableFile(docs)?.id ?? null;
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
    activeFolderId.value = null;
    loadExpandedState();
    const files = byCategory.value[activeCategory.value] || [];
    const docs = files.filter((f) => !f.is_folder);
    selectedId.value = firstAvailableFile(docs)?.id ?? null;
});

const totalCount = computed(() => props.attachments.filter((a) => !a.is_folder).length);

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
    if (file.is_external_link && file.is_pdf) return 'PDF';
    return fileExt(file.original_name);
};

const formatSize = (bytes, file = null) => {
    if (file?.is_external_link) return 'Link tài liệu';
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

const uploadPayloadExtra = (category) => {
    const payload = {};
    if (activeFolderId.value && category === activeCategory.value) {
        payload.parent_id = activeFolderId.value;
    }
    return payload;
};

const uploadFiles = (category, fileList, inputEl = null) => {
    const files = [...(fileList || [])];
    if (!files.length || !props.canUpload) return;
    uploadingCategory.value = category;
    router.post(`/projects/${props.projectId}/attachments`, { category, ...uploadPayloadExtra(category), files }, {
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
    if (activeFolderId.value) {
        linkForm.parent_id = activeFolderId.value;
    }
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
    if (activeFolderId.value) {
        linkForm.parent_id = activeFolderId.value;
    }
    linkForm.post(`/projects/${props.projectId}/attachments`, {
        preserveScroll: true,
        onSuccess: () => {
            linkDraft.clear();
            closeLinkModal();
        },
        onError: () => toast.error('Không thể thêm link. Kiểm tra URL (Google Docs, Sheets hoặc PDF).'),
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

const selectFile = (file) => {
    if (file.is_folder) return;
    selectedId.value = file.id;
};

const onSelectFolder = (folder) => {
    activeFolderId.value = folder.id;
    let pid = folder.parent_id ?? null;
    while (pid) {
        delete expandedIds[pid];
        const parent = props.attachments.find((a) => a.id === pid);
        pid = parent?.parent_id ?? null;
    }
    if (expandedIds[folder.id] === false) {
        delete expandedIds[folder.id];
    }
    persistExpandedState();
};

const onToggleFolderExpand = (id) => {
    if (expandedIds[id] === false) {
        delete expandedIds[id];
    } else {
        expandedIds[id] = false;
    }
    persistExpandedState();
};

const expandAllFolders = () => {
    categoryFiles.value.filter((f) => f.is_folder).forEach((f) => {
        delete expandedIds[f.id];
    });
    persistExpandedState();
};

const collapseAllFolders = () => {
    categoryFiles.value.filter((f) => f.is_folder).forEach((f) => {
        expandedIds[f.id] = false;
    });
    persistExpandedState();
};

const openFolderModal = (parentId = undefined) => {
    folderForm.reset();
    folderForm.category = activeCategory.value;
    folderForm.parent_id = parentId !== undefined ? parentId : activeFolderId.value;
    folderForm.is_folder = true;
    folderForm.clearErrors();
    showFolderModal.value = true;
};

const openSubfolderModal = (parentId) => {
    activeFolderId.value = parentId;
    openFolderModal(parentId);
};

const closeFolderModal = () => {
    showFolderModal.value = false;
    folderForm.reset();
    folderForm.clearErrors();
};

const submitFolder = () => {
    if (!props.canUpload) return;
    folderForm.post(`/projects/${props.projectId}/attachments`, {
        preserveScroll: true,
        onSuccess: () => {
            closeFolderModal();
            const parentId = folderForm.parent_id;
            if (parentId) {
                delete expandedIds[parentId];
                activeFolderId.value = parentId;
                persistExpandedState();
            }
        },
        onError: () => toast.error('Không thể tạo thư mục.'),
    });
};

const clearActiveFolder = () => {
    activeFolderId.value = null;
};

const removeFile = (file) => {
    const stats = file.is_folder ? countFolderContents(file.id, categoryFiles.value) : { subfolders: 0, files: 0 };
    const parts = [];
    if (stats.subfolders > 0) parts.push(`${stats.subfolders} thư mục con`);
    if (stats.files > 0) parts.push(`${stats.files} tài liệu`);
    const suffix = parts.length ? ` và ${parts.join(', ')} bên trong` : '';
    confirmDelete(
        `Xoá "${file.original_name}"${suffix}?`,
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
    folder_created: 'folder',
    folder_renamed: 'edit',
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
    folder_created: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    folder_renamed: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
}[event] || 'bg-slate-100 text-slate-500 dark:bg-slate-800');
</script>

<template>
  <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-white dark:bg-slate-900">
    <!-- Danh mục + tổng số -->
    <div class="shrink-0 border-b border-slate-200 bg-slate-50/80 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/80">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <p :class="sectionLabelClass">
          Danh mục tài liệu
        </p>
        <span class="text-xs text-slate-500">
          Tổng
          <span class="font-semibold tabular-nums text-brand">{{ totalCount }}</span>
          file
        </span>
      </div>
      <nav
        class="mt-2 overflow-x-auto overscroll-x-contain"
        aria-label="Danh mục tài liệu"
      >
        <div
          class="inline-flex min-w-0 items-stretch gap-0.5 rounded-lg border border-slate-200/90 bg-white p-0.5 shadow-sm dark:border-slate-700 dark:bg-slate-900"
          role="group"
        >
          <button
            v-for="cat in categories"
            :key="cat.value"
            type="button"
            class="inline-flex shrink-0 items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium transition sm:px-2.5"
            :class="activeCategory === cat.value
              ? 'bg-brand/10 text-brand ring-1 ring-brand/15 dark:bg-brand/20 dark:text-brand-100'
              : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200'"
            @click="activeCategory = cat.value"
          >
            <AppIcon
              :name="cat.icon"
              :size="13"
            />
            <span class="whitespace-nowrap">{{ cat.label }}</span>
            <span
              class="inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold tabular-nums"
              :class="categoryItemCount(cat.value)
                ? (colorBadge[cat.color] || 'bg-brand/10 text-brand')
                : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500'"
            >{{ categoryItemCount(cat.value) }}</span>
          </button>
        </div>
      </nav>
    </div>

    <div
      v-if="canUpload"
      class="shrink-0 border-b border-slate-100 bg-white px-3 py-1.5 dark:border-slate-700 dark:bg-slate-900"
      :class="!categoryItemCount(activeCategory) && !categoryFiles.some((f) => f.is_folder) ? 'space-y-1.5' : ''"
    >
      <p
        v-if="canUpload"
        class="text-[11px] text-slate-500 sm:text-xs"
      >
        Đích lưu:
        <span class="font-medium text-slate-700 dark:text-slate-200">{{ activeFolderLabel }}</span>
        <button
          v-if="activeFolderId"
          type="button"
          class="ml-1 text-brand hover:underline"
          @click="clearActiveFolder"
        >
          (về gốc)
        </button>
      </p>
      <div class="flex flex-wrap items-center justify-end gap-2">
        <button
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 border border-amber-200/80 bg-amber-50/50 px-2.5 text-xs font-medium text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100 sm:text-sm"
          @click="openFolderModal"
        >
          <AppIcon
            name="folder"
            :size="14"
          />
          Tạo thư mục
        </button>
        <button
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 border border-slate-200 px-2.5 text-xs font-medium dark:border-slate-600 sm:text-sm"
          @click="openAddLinkModal"
        >
          <AppIcon
            name="link"
            :size="14"
          />
          Thêm link tài liệu
        </button>
        <button
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 border border-slate-200 px-2.5 text-xs font-medium dark:border-slate-600 sm:text-sm"
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
        v-if="!categoryItemCount(activeCategory) && !categoryFiles.some((f) => f.is_folder)"
        class="rounded-lg border-2 border-dashed px-4 py-2.5 text-center transition"
        :class="dragging
          ? 'border-brand bg-brand/5'
          : 'border-slate-200 bg-slate-50/80 dark:border-slate-600 dark:bg-slate-800/40'"
        @dragover.prevent="dragging = true"
        @dragleave="dragging = false"
        @drop.prevent="onDrop(activeCategory, $event)"
      >
        <p class="text-xs font-medium text-slate-600 dark:text-slate-300 sm:text-sm">
          Kéo thả file vào đây · PDF, Office, ảnh, ZIP… · tối đa 20MB/file
        </p>
      </div>
    </div>

    <!-- Danh sách | Preview full-height | Chi tiết (sidebar) -->
    <div
      class="grid min-h-0 flex-1 overflow-hidden max-lg:grid-rows-[minmax(0,34vh)_minmax(0,1fr)] lg:grid-rows-1"
      :class="workspaceGridClass"
    >
      <div class="flex min-h-0 flex-col overflow-hidden border-b border-slate-200 bg-slate-50/50 lg:border-b-0 lg:border-r dark:border-slate-700 dark:bg-slate-900/50">
        <div
          class="shrink-0 space-y-1 border-b border-slate-200/80 bg-white px-2.5 py-1.5 dark:border-slate-700 dark:bg-slate-900"
        >
          <div class="flex flex-wrap items-center justify-between gap-1">
            <p :class="sectionLabelClass">
              Cây thư mục
              <span class="font-normal normal-case text-slate-400">
                ({{ categoryItemCount(activeCategory) }} file)
              </span>
            </p>
            <div
              v-if="categoryFiles.some((f) => f.is_folder)"
              class="flex shrink-0 gap-1"
            >
              <button
                type="button"
                class="rounded px-1.5 py-0.5 text-[10px] font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                @click="expandAllFolders"
              >
                Mở tất cả
              </button>
              <button
                type="button"
                class="rounded px-1.5 py-0.5 text-[10px] font-medium text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                @click="collapseAllFolders"
              >
                Thu gọn
              </button>
            </div>
          </div>
          <nav
            v-if="folderPath.length"
            class="flex flex-wrap items-center gap-1 text-[11px] text-slate-500"
            aria-label="Vị trí thư mục"
          >
            <button
              type="button"
              class="rounded px-1 py-0.5 hover:bg-slate-100 dark:hover:bg-slate-800"
              @click="clearActiveFolder"
            >
              Gốc
            </button>
            <template
              v-for="crumb in folderPath"
              :key="crumb.id"
            >
              <AppIcon
                name="chevron-right"
                :size="10"
                class="text-slate-300"
              />
              <button
                type="button"
                class="max-w-[8rem] truncate rounded px-1 py-0.5 font-medium text-brand hover:bg-brand/5"
                @click="onSelectFolder(crumb)"
              >
                {{ crumb.original_name }}
              </button>
            </template>
          </nav>
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
            <p class="mt-2 text-xs text-slate-400 sm:text-sm">
              {{ canUpload ? 'Chưa có thư mục hay tài liệu — tạo thư mục hoặc tải file.' : 'Chưa có tài liệu.' }}
            </p>
            <div
              v-if="canUpload"
              class="mt-3 flex flex-wrap items-center justify-center gap-2"
            >
              <button
                type="button"
                class="btn-ghost inline-flex h-9 items-center gap-1.5 border border-slate-200 px-3 text-xs font-medium sm:text-sm"
                @click="openFolderModal"
              >
                <AppIcon
                  name="folder"
                  :size="14"
                />
                Tạo thư mục
              </button>
              <button
                type="button"
                class="text-xs font-medium text-brand hover:underline sm:text-sm"
                @click="pickFiles(activeCategory)"
              >
                Tải file
              </button>
            </div>
          </div>
          <ProjectDocumentTree
            v-else
            :nodes="documentTree"
            :selected-id="selectedId"
            :active-folder-id="activeFolderId"
            :expanded-ids="expandedIds"
            :list-badge="listBadge"
            :format-size="formatSize"
            :can-upload="canUpload"
            @select-file="selectFile"
            @select-folder="onSelectFolder"
            @toggle-folder="onToggleFolderExpand"
            @create-subfolder="openSubfolderModal"
          />
        </div>
      </div>

      <!-- Preview — full chiều cao còn lại, không cuộn -->
      <div class="flex min-h-0 flex-col overflow-hidden bg-slate-100/80 dark:bg-slate-950 lg:border-l lg:border-slate-200/80 dark:lg:border-slate-800">
        <template v-if="selected">
          <div class="flex shrink-0 items-center gap-2 border-b border-slate-200/80 bg-white px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900">
            <h3 class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
              {{ selected.original_name }}
            </h3>
            <button
              type="button"
              class="btn-ghost inline-flex h-8 shrink-0 items-center gap-1 px-2 text-xs font-medium lg:hidden"
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
          <div class="flex min-h-0 flex-1 flex-col overflow-hidden p-1.5">
            <DocumentPreviewPane
              class="min-h-0 flex-1"
              :file="selected"
            />
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
          <p class="mt-2 text-xs sm:text-sm">
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
      title="Thêm link tài liệu"
      max-width="max-w-md"
      :dirty="Boolean(linkForm.title || linkForm.external_url)"
      :on-save-draft="saveLinkDraftOnClose"
      @close="closeLinkModal"
    >
      <p class="mb-4 text-sm text-slate-500">
        Dán link Google Docs, Google Sheets hoặc PDF vào
        <span class="font-medium text-slate-700">«{{ activeCat?.label }}»</span>
        · thư mục đích:
        <span class="font-medium text-slate-700">{{ activeFolderLabel }}</span>.
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
          >Link (Google Docs, Sheets hoặc PDF)</label>
          <input
            id="doc-link-url"
            v-model="linkForm.external_url"
            type="url"
            class="input mt-1 w-full text-sm"
            placeholder="https://docs.google.com/… hoặc https://…/file.pdf"
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

    <Modal
      ref="folderModalRef"
      :show="showFolderModal"
      title="Tạo thư mục"
      max-width="max-w-md"
      :dirty="Boolean(folderForm.folder_name)"
      @close="closeFolderModal"
    >
      <p class="mb-4 text-sm text-slate-500">
        Thư mục trong
        <span class="font-medium text-slate-700">«{{ activeCat?.label }}»</span>
        <template v-if="activeFolderId">
          · bên trong
          <span class="font-medium text-slate-700">{{ activeFolderLabel }}</span>
        </template>
        <template v-else>
          · cấp gốc danh mục
        </template>
      </p>
      <div>
        <label
          for="doc-folder-name"
          class="text-xs font-medium text-slate-500"
        >Tên thư mục</label>
        <input
          id="doc-folder-name"
          v-model="folderForm.folder_name"
          type="text"
          class="input mt-1 w-full text-sm"
          placeholder="VD: Hợp đồng · Thiết kế · Bàn giao"
          maxlength="255"
          @keyup.enter="submitFolder"
        >
        <p
          v-if="folderForm.errors.folder_name"
          class="mt-1 text-xs text-rose-600"
        >
          {{ folderForm.errors.folder_name }}
        </p>
      </div>
      <div class="mt-5 flex justify-end gap-2">
        <button
          type="button"
          class="btn-ghost text-sm"
          @click="closeFolderModal"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary text-sm"
          :disabled="folderForm.processing || !folderForm.folder_name.trim()"
          @click="submitFolder"
        >
          {{ folderForm.processing ? 'Đang tạo…' : 'Tạo thư mục' }}
        </button>
      </div>
    </Modal>
  </div>
</template>

