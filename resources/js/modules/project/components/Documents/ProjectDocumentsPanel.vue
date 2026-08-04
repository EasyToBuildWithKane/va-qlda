<script setup>
import { ref, computed, watch, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue';
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
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';

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
const showFileModal = ref(false);
const fileModalRef = ref(null);
const folderNameInput = ref(null);
const fileNameInput = ref(null);
const addMenuOpen = ref(false);
const addMenuRef = ref(null);

const { panelStyle: addMenuStyle } = useFixedDropdownAnchor(
    () => addMenuRef.value,
    addMenuOpen,
    { width: 220, zIndex: 200 },
);

const closeAddMenu = () => {
    addMenuOpen.value = false;
};

const onDocPanelPointerDown = (event) => {
    if (!addMenuOpen.value) return;
    const anchor = addMenuRef.value;
    const panel = document.getElementById('project-docs-add-menu');
    if (anchor?.contains(event.target) || panel?.contains(event.target)) return;
    closeAddMenu();
};

onMounted(() => {
    loadExpandedState();
    document.addEventListener('mousedown', onDocPanelPointerDown);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocPanelPointerDown);
});

const runAddAction = (action) => {
    closeAddMenu();
    action();
};

const NEW_FILE_TYPES = [
    { value: 'txt', label: 'Văn bản', ext: '.txt', icon: 'documents' },
    { value: 'md', label: 'Markdown', ext: '.md', icon: 'documents' },
    { value: 'csv', label: 'CSV', ext: '.csv', icon: 'documents' },
    { value: 'json', label: 'JSON', ext: '.json', icon: 'documents' },
];

const folderForm = useForm({
    category: '',
    folder_name: '',
    parent_id: null,
    is_folder: true,
});

const newFileForm = useForm({
    category: '',
    file_name: '',
    file_type: 'txt',
    parent_id: null,
    is_new_file: true,
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

/** Danh sách hẹp — phần còn lại ưu tiên preview (chi tiết qua drawer). */
const workspaceGridClass = 'lg:grid-cols-[minmax(180px,220px)_minmax(0,1fr)]';

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
    if (!props.canUpload) return;
    dragging.value = true;
};

const onDragLeave = (event) => {
    const related = event.relatedTarget;
    if (related && event.currentTarget?.contains?.(related)) return;
    dragging.value = false;
};

const onDrop = (category, event) => {
    dragging.value = false;
    uploadFiles(category, event.dataTransfer?.files);
};

const folderPathLabel = computed(() => {
    const parts = [activeCat.value?.label || 'Danh mục'];
    let pid = folderForm.parent_id;
    const chain = [];
    while (pid) {
        const node = props.attachments.find((a) => a.id === pid);
        if (!node) break;
        chain.unshift(node.original_name);
        pid = node.parent_id ?? null;
    }
    return [...parts, ...chain].join(' / ');
});

const filePathLabel = computed(() => {
    const parts = [activeCat.value?.label || 'Danh mục'];
    let pid = newFileForm.parent_id;
    const chain = [];
    while (pid) {
        const node = props.attachments.find((a) => a.id === pid);
        if (!node) break;
        chain.unshift(node.original_name);
        pid = node.parent_id ?? null;
    }
    return [...parts, ...chain].join(' / ');
});

const selectedNewFileType = computed(() => (
    NEW_FILE_TYPES.find((t) => t.value === newFileForm.file_type) || NEW_FILE_TYPES[0]
));

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

const focusFolderInput = async () => {
    await nextTick();
    folderNameInput.value?.focus?.();
    folderNameInput.value?.select?.();
};

const focusFileInput = async () => {
    await nextTick();
    fileNameInput.value?.focus?.();
    fileNameInput.value?.select?.();
};

const openFolderModal = (parentId = undefined) => {
    folderForm.reset();
    folderForm.category = activeCategory.value;
    folderForm.parent_id = parentId !== undefined ? parentId : activeFolderId.value;
    folderForm.is_folder = true;
    folderForm.clearErrors();
    showFolderModal.value = true;
    focusFolderInput();
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

const openFileModal = (parentId = undefined) => {
    newFileForm.reset();
    newFileForm.category = activeCategory.value;
    newFileForm.parent_id = parentId !== undefined ? parentId : activeFolderId.value;
    newFileForm.file_type = 'txt';
    newFileForm.file_name = '';
    newFileForm.is_new_file = true;
    newFileForm.clearErrors();
    showFileModal.value = true;
    focusFileInput();
};

const openCreateInFolder = (parentId) => {
    activeFolderId.value = parentId;
    openFileModal(parentId);
};

const closeFileModal = () => {
    showFileModal.value = false;
    newFileForm.reset();
    newFileForm.clearErrors();
};

const firstErrorMessage = (errors, keys, fallback) => {
    if (!errors || typeof errors !== 'object') return fallback;
    for (const key of keys) {
        const val = errors[key];
        if (Array.isArray(val) && val[0]) return val[0];
        if (typeof val === 'string' && val) return val;
    }
    return fallback;
};

const folderErrorMessage = (errors) => firstErrorMessage(
    errors,
    ['folder_name', 'parent_id', 'is_folder', 'category', 'files'],
    'Không thể tạo thư mục.',
);

const fileErrorMessage = (errors) => firstErrorMessage(
    errors,
    ['file_name', 'file_type', 'parent_id', 'is_new_file', 'category', 'files'],
    'Không thể tạo file.',
);

const afterCreateInParent = (parentId) => {
    if (parentId) {
        delete expandedIds[parentId];
        activeFolderId.value = parentId;
        persistExpandedState();
    }
};

const submitFolder = () => {
    if (!props.canUpload || !folderForm.folder_name.trim()) return;
    folderForm.clearErrors();
    folderForm.folder_name = folderForm.folder_name.trim();
    folderForm.parent_id = folderForm.parent_id ? Number(folderForm.parent_id) : null;
    const parentId = folderForm.parent_id;
    folderForm.post(`/projects/${props.projectId}/attachments`, {
        preserveScroll: true,
        onSuccess: () => {
            closeFolderModal();
            afterCreateInParent(parentId);
        },
        onError: (errors) => toast.error(folderErrorMessage(errors)),
    });
};

const submitNewFile = () => {
    if (!props.canUpload || !newFileForm.file_name.trim()) return;
    newFileForm.clearErrors();
    newFileForm.file_name = newFileForm.file_name.trim();
    newFileForm.parent_id = newFileForm.parent_id ? Number(newFileForm.parent_id) : null;
    newFileForm.is_new_file = true;
    const parentId = newFileForm.parent_id;
    newFileForm.post(`/projects/${props.projectId}/attachments`, {
        preserveScroll: true,
        onSuccess: () => {
            closeFileModal();
            afterCreateInParent(parentId);
        },
        onError: (errors) => toast.error(fileErrorMessage(errors)),
    });
};

const removeFile = (file) => {
    const stats = file.is_folder ? countFolderContents(file.id, categoryFiles.value) : { subfolders: 0, files: 0 };
    const parts = [];
    if (stats.subfolders > 0) parts.push(`${stats.subfolders} thư mục`);
    if (stats.files > 0) parts.push(`${stats.files} tài liệu`);
    const suffix = parts.length ? ` và ${parts.join(', ')} bên trong` : '';
    confirmDelete(
        `Xoá "${file.original_name}"${suffix}?`,
        () => router.delete(`/projects/${props.projectId}/attachments/${file.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                if (activeFolderId.value === file.id) {
                    activeFolderId.value = null;
                }
            },
        }),
        { title: file.is_folder ? 'Xoá thư mục' : 'Xoá tài liệu' },
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
    file_created: 'documents',
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
    return ext.toUpperCase() || 'Tài liệu';
};

const activityTone = (event) => ({
    uploaded: 'bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-400',
    file_created: 'bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-400',
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
    <!-- Danh mục + hành động (một hàng gọn) -->
    <div class="shrink-0 border-b border-slate-200 bg-slate-50/80 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-900/80">
      <div class="flex min-w-0 flex-wrap items-center gap-1.5 lg:flex-nowrap">
        <nav
          class="min-w-0 flex-1 overflow-x-auto overscroll-x-contain"
          aria-label="Danh mục tài liệu"
        >
          <div
            class="inline-flex min-w-0 items-stretch gap-0.5 rounded-md border border-slate-200/90 bg-white p-0.5 shadow-sm dark:border-slate-700 dark:bg-slate-900"
            role="group"
          >
            <button
              v-for="cat in categories"
              :key="cat.value"
              type="button"
              class="inline-flex shrink-0 items-center gap-1 rounded px-1.5 py-1 text-[11px] font-medium transition sm:gap-1.5 sm:px-2 sm:text-xs"
              :class="activeCategory === cat.value
                ? 'bg-brand/10 text-brand ring-1 ring-brand/15 dark:bg-brand/20 dark:text-brand-100'
                : 'text-slate-600 hover:bg-slate-50 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200'"
              @click="activeCategory = cat.value"
            >
              <AppIcon
                :name="cat.icon"
                :size="12"
              />
              <span class="whitespace-nowrap">{{ cat.label }}</span>
              <span
                class="inline-flex h-3.5 min-w-3.5 items-center justify-center rounded-full px-1 text-[9px] font-semibold tabular-nums"
                :class="categoryItemCount(cat.value)
                  ? (colorBadge[cat.color] || 'bg-brand/10 text-brand')
                  : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500'"
              >{{ categoryItemCount(cat.value) }}</span>
            </button>
          </div>
        </nav>

        <div class="ml-auto flex shrink-0 items-center gap-1.5">
          <span
            class="hidden tabular-nums text-[11px] text-slate-500 sm:inline"
            :title="`${totalCount} file`"
          >
            <span class="font-semibold text-brand">{{ totalCount }}</span>
          </span>
          <template v-if="canUpload">
            <div
              ref="addMenuRef"
              class="relative"
            >
              <button
                type="button"
                class="btn-ghost inline-flex h-8 items-center gap-1 border border-slate-200 px-2 text-xs font-medium dark:border-slate-600"
                :aria-expanded="addMenuOpen"
                aria-haspopup="menu"
                aria-controls="project-docs-add-menu"
                @click="addMenuOpen = !addMenuOpen"
              >
                <AppIcon
                  name="plus"
                  :size="13"
                />
                Thêm
                <AppIcon
                  name="chevron-down"
                  :size="11"
                  class="opacity-60"
                />
              </button>
            </div>
            <Teleport to="body">
              <div
                v-if="addMenuOpen"
                id="project-docs-add-menu"
                role="menu"
                class="overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
                :style="addMenuStyle"
              >
                <button
                  type="button"
                  role="menuitem"
                  class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                  @click="runAddAction(() => openFolderModal())"
                >
                  <AppIcon
                    name="folder"
                    :size="14"
                    class="text-amber-600"
                  />
                  Thư mục
                </button>
                <button
                  type="button"
                  role="menuitem"
                  class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                  @click="runAddAction(() => openFileModal())"
                >
                  <AppIcon
                    name="documents"
                    :size="14"
                    class="text-sky-600"
                  />
                  File trống
                </button>
                <button
                  type="button"
                  role="menuitem"
                  class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800"
                  @click="runAddAction(openAddLinkModal)"
                >
                  <AppIcon
                    name="link"
                    :size="14"
                    class="text-emerald-600"
                  />
                  Link ngoài
                </button>
              </div>
            </Teleport>
            <button
              type="button"
              class="btn-primary inline-flex h-8 items-center gap-1 px-2.5 text-xs font-medium"
              :disabled="uploadingCategory === activeCategory"
              @click="pickFiles(activeCategory)"
            >
              <AppIcon
                :name="uploadingCategory === activeCategory ? 'refresh' : 'upload'"
                :size="13"
                :class="uploadingCategory === activeCategory ? 'animate-spin' : ''"
              />
              {{ uploadingCategory === activeCategory ? 'Đang tải…' : 'Tải lên' }}
            </button>
            <input
              :ref="(el) => setFileInput(activeCategory, el)"
              type="file"
              class="hidden"
              multiple
              :accept="acceptFor(activeCategory)"
              @change="onFilesSelected(activeCategory, $event)"
            >
          </template>
        </div>
      </div>
    </div>

    <!-- Empty: một khối -->
    <div
      v-if="!categoryFiles.length"
      class="relative flex min-h-0 flex-1 flex-col items-center justify-center overflow-hidden bg-slate-50/40 px-4 py-8 text-center dark:bg-slate-950/40"
      @dragover.prevent="onDragOver"
      @dragleave="onDragLeave"
      @drop.prevent="onDrop(activeCategory, $event)"
    >
      <div
        v-if="canUpload && dragging"
        class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-brand/10 backdrop-blur-[1px]"
      >
        <div class="flex flex-col items-center gap-1.5 rounded-lg border-2 border-dashed border-brand bg-white/95 px-5 py-4 shadow-sm dark:bg-slate-900/95">
          <AppIcon
            name="upload"
            :size="22"
            class="text-brand"
          />
          <p class="text-sm font-semibold text-brand">
            Thả để tải lên
          </p>
        </div>
      </div>
      <AppIcon
        :name="activeCat?.icon || 'documents'"
        :size="28"
        class="text-slate-300 dark:text-slate-600"
      />
      <p class="mt-2 text-sm font-medium text-slate-600 dark:text-slate-300">
        {{ canUpload ? 'Chưa có thư mục hoặc tài liệu' : 'Chưa có tài liệu' }}
      </p>
      <p
        v-if="canUpload"
        class="mt-1 max-w-xs text-xs text-slate-500"
      >
        Kéo thả, hoặc <span class="font-medium text-slate-600 dark:text-slate-300">Thêm</span> / <span class="font-medium text-slate-600 dark:text-slate-300">Tải lên</span>.
      </p>
    </div>

    <!-- Danh sách | Preview (chi tiết qua drawer) -->
    <div
      v-else
      class="grid min-h-0 flex-1 overflow-hidden max-lg:grid-rows-[minmax(0,28vh)_minmax(0,1fr)] lg:grid-rows-1"
      :class="workspaceGridClass"
    >
      <div class="flex min-h-0 flex-col overflow-hidden border-b border-slate-200 bg-slate-50/50 lg:border-b-0 lg:border-r dark:border-slate-700 dark:bg-slate-900/50">
        <div
          v-if="categoryFiles.some((f) => f.is_folder)"
          class="flex shrink-0 items-center justify-end gap-1 border-b border-slate-200/80 bg-white px-1.5 py-0.5 dark:border-slate-700 dark:bg-slate-900"
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
        <div
          class="relative min-h-0 flex-1 overflow-y-auto bg-white dark:bg-slate-900"
          @dragover.prevent="onDragOver"
          @dragleave="onDragLeave"
          @drop.prevent="onDrop(activeCategory, $event)"
        >
          <div
            v-if="canUpload && dragging"
            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-brand/10 backdrop-blur-[1px]"
          >
            <div class="flex flex-col items-center gap-1.5 rounded-lg border-2 border-dashed border-brand bg-white/95 px-5 py-4 shadow-sm dark:bg-slate-900/95">
              <AppIcon
                name="upload"
                :size="22"
                class="text-brand"
              />
              <p class="text-sm font-semibold text-brand">
                Thả để tải lên
              </p>
            </div>
          </div>
          <ProjectDocumentTree
            :nodes="documentTree"
            :selected-id="selectedId"
            :active-folder-id="activeFolderId"
            :expanded-ids="expandedIds"
            :list-badge="listBadge"
            :format-size="formatSize"
            :can-upload="canUpload"
            :can-delete="canDelete"
            @select-file="selectFile"
            @select-folder="onSelectFolder"
            @toggle-folder="onToggleFolderExpand"
            @create-subfolder="openSubfolderModal"
            @create-file="openCreateInFolder"
            @delete-item="removeFile"
          />
        </div>
      </div>

      <!-- Preview — full chiều cao/rộng còn lại -->
      <div class="flex min-h-0 flex-col overflow-hidden bg-slate-100/60 dark:bg-slate-950">
        <template v-if="selected">
          <div class="flex shrink-0 items-center gap-1.5 border-b border-slate-200/80 bg-white px-1.5 py-1 dark:border-slate-700 dark:bg-slate-900">
            <h3 class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-800 dark:text-slate-100 sm:text-sm">
              {{ selected.original_name }}
            </h3>
            <button
              type="button"
              class="btn-ghost inline-flex h-7 shrink-0 items-center gap-1 px-1.5 text-[11px] font-medium"
              @click="detailDrawerOpen = true"
            >
              <AppIcon
                name="info"
                :size="13"
              />
              <span class="hidden sm:inline">Chi tiết</span>
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
                  :size="13"
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
                  :size="13"
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
                  :size="13"
                />
              </button>
            </div>
          </div>
          <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            <DocumentPreviewPane
              class="min-h-0 flex-1"
              :file="selected"
            />
          </div>
        </template>
        <div
          v-else
          class="flex min-h-0 flex-1 flex-col items-center justify-center p-4 text-center text-slate-400"
        >
          <AppIcon
            name="documents"
            :size="24"
            class="opacity-40"
          />
          <p class="mt-1.5 text-xs">
            Chọn tài liệu để xem trước.
          </p>
        </div>
      </div>
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
      max-width="max-w-sm"
      :dirty="Boolean(linkForm.title || linkForm.external_url)"
      :on-save-draft="saveLinkDraftOnClose"
      @close="closeLinkModal"
    >
      <div class="space-y-2.5">
        <div>
          <label
            for="doc-link-title"
            class="text-xs font-medium text-slate-500"
          >Tên hiển thị (tuỳ chọn)</label>
          <input
            id="doc-link-title"
            v-model="linkForm.title"
            type="text"
            class="input mt-1 h-9 w-full text-sm"
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
            class="input mt-1 h-9 w-full text-sm"
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
      <div class="mt-3 flex justify-end gap-2">
        <button
          type="button"
          class="btn-ghost h-8 text-xs"
          @click="requestCloseLinkModal"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary h-8 text-xs"
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
      max-width="max-w-sm"
      :dirty="Boolean(folderForm.folder_name)"
      @close="closeFolderModal"
    >
      <p class="mb-2 text-xs text-slate-500">
        Vị trí: <span class="font-medium text-slate-700 dark:text-slate-300">{{ folderPathLabel }}</span>
      </p>
      <div>
        <label
          for="doc-folder-name"
          class="text-xs font-medium text-slate-500"
        >Tên thư mục</label>
        <input
          id="doc-folder-name"
          ref="folderNameInput"
          v-model="folderForm.folder_name"
          type="text"
          class="input mt-1 h-9 w-full text-sm"
          placeholder="Nhập tên thư mục"
          maxlength="255"
          autocomplete="off"
          @keyup.enter="submitFolder"
        >
        <p
          v-if="folderForm.errors.folder_name"
          class="mt-1 text-xs text-rose-600"
        >
          {{ folderForm.errors.folder_name }}
        </p>
      </div>
      <div class="mt-3 flex justify-end gap-2">
        <button
          type="button"
          class="btn-ghost h-8 text-xs"
          @click="closeFolderModal"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary inline-flex h-8 items-center gap-1 text-xs"
          :disabled="folderForm.processing || !folderForm.folder_name.trim()"
          @click="submitFolder"
        >
          <AppIcon
            name="folder"
            :size="13"
          />
          {{ folderForm.processing ? 'Đang tạo…' : 'Tạo thư mục' }}
        </button>
      </div>
    </Modal>

    <Modal
      ref="fileModalRef"
      :show="showFileModal"
      title="Tạo file"
      max-width="max-w-sm"
      :dirty="Boolean(newFileForm.file_name)"
      @close="closeFileModal"
    >
      <p class="mb-2 text-xs text-slate-500">
        Vị trí: <span class="font-medium text-slate-700 dark:text-slate-300">{{ filePathLabel }}</span>
      </p>
      <div class="space-y-2.5">
        <div>
          <p class="text-xs font-medium text-slate-500">
            Loại file
          </p>
          <div
            class="mt-1 grid grid-cols-4 gap-1"
            role="radiogroup"
            aria-label="Loại file"
          >
            <button
              v-for="type in NEW_FILE_TYPES"
              :key="type.value"
              type="button"
              role="radio"
              class="rounded-md border px-1.5 py-1.5 text-center transition"
              :class="newFileForm.file_type === type.value
                ? 'border-brand/40 bg-brand/5 ring-1 ring-brand/20'
                : 'border-slate-200 hover:border-slate-300 dark:border-slate-600'"
              :aria-checked="newFileForm.file_type === type.value"
              :title="type.label"
              @click="newFileForm.file_type = type.value"
            >
              <span class="block text-[10px] font-bold uppercase text-slate-700 dark:text-slate-200">{{ type.ext.replace('.', '') }}</span>
            </button>
          </div>
          <p
            v-if="newFileForm.errors.file_type"
            class="mt-1 text-xs text-rose-600"
          >
            {{ newFileForm.errors.file_type }}
          </p>
        </div>

        <div>
          <label
            for="doc-new-file-name"
            class="text-xs font-medium text-slate-500"
          >Tên file</label>
          <div class="relative mt-1">
            <input
              id="doc-new-file-name"
              ref="fileNameInput"
              v-model="newFileForm.file_name"
              type="text"
              class="input h-9 w-full pr-12 text-sm"
              placeholder="Nhập tên file"
              maxlength="200"
              autocomplete="off"
              @keyup.enter="submitNewFile"
            >
            <span class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-xs font-medium text-slate-400">
              {{ selectedNewFileType.ext }}
            </span>
          </div>
          <p
            v-if="newFileForm.errors.file_name"
            class="mt-1 text-xs text-rose-600"
          >
            {{ newFileForm.errors.file_name }}
          </p>
        </div>
      </div>
      <div class="mt-3 flex justify-end gap-2">
        <button
          type="button"
          class="btn-ghost h-8 text-xs"
          @click="closeFileModal"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary inline-flex h-8 items-center gap-1 text-xs"
          :disabled="newFileForm.processing || !newFileForm.file_name.trim()"
          @click="submitNewFile"
        >
          <AppIcon
            name="documents"
            :size="13"
          />
          {{ newFileForm.processing ? 'Đang tạo…' : 'Tạo file' }}
        </button>
      </div>
    </Modal>
  </div>
</template>

