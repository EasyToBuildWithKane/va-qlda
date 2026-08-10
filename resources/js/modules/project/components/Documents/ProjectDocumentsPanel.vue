<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { useToast } from '@/shared/composables/useToast';
import Modal from '@/Components/Ui/Modal.vue';
import DocumentPreviewPane from '@/modules/project/components/Documents/DocumentPreviewPane.vue';
import ProjectDocumentDetailAside from '@/modules/project/components/Documents/ProjectDocumentDetailAside.vue';
import DocumentFolderCard from '@/modules/project/components/Documents/DocumentFolderCard.vue';
import DocumentFileCard from '@/modules/project/components/Documents/DocumentFileCard.vue';
import DocumentFilesTable from '@/modules/project/components/Documents/DocumentFilesTable.vue';
import Drawer from '@/Components/Ui/Drawer.vue';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta } from '@/composables/useModalDraftHelpers';
import { useFixedDropdownAnchor } from '@/shared/composables/useFixedDropdownAnchor';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    projectId: { type: [Number, String], required: true },
    attachments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    canUpload: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const emit = defineEmits(['open-task']);

const toast = useToast();
const confirmDelete = useConfirmDelete();
/** null = gốc kiểu Drive (danh mục là thư mục cấp 1) */
const activeCategory = ref(null);
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
const previewModalOpen = ref(false);
const activeFolderId = ref(null);
const showFolderModal = ref(false);
const folderModalRef = ref(null);
const showFileModal = ref(false);
const fileModalRef = ref(null);
const showRenameModal = ref(false);
const renameModalRef = ref(null);
const renameNameInput = ref(null);
const folderNameInput = ref(null);
const fileNameInput = ref(null);
const addMenuOpen = ref(false);
const addMenuRef = ref(null);
const leftPanelCollapsed = ref(false);
const rightPanelCollapsed = ref(false);
const docsViewMode = ref('list');
const selectedRowIds = ref([]);
const previewEditing = ref(false);
const previewDraft = ref('');
const previewPaneRef = ref(null);
const contentSaving = ref(false);

const docsViewStorageKey = computed(() => `va-project-docs-view:${props.projectId}`);

const loadDocsViewMode = () => {
    try {
        const saved = localStorage.getItem(docsViewStorageKey.value);
        if (saved === 'grid' || saved === 'list') docsViewMode.value = saved;
    } catch {
        /* ignore */
    }
};

const setDocsViewMode = (mode) => {
    docsViewMode.value = mode;
    try {
        localStorage.setItem(docsViewStorageKey.value, mode);
    } catch {
        /* ignore */
    }
};

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
    loadDocsViewMode();
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

const renameForm = useForm({
    title: '',
});

const renameTarget = ref(null);

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

const byCategory = computed(() => {
    const map = {};
    props.categories.forEach((c) => { map[c.value] = []; });
    props.attachments.forEach((f) => {
        if (map[f.category]) map[f.category].push(f);
    });
    return map;
});

const isDriveRoot = computed(() => activeCategory.value == null);
const activeCat = computed(() => props.categories.find((c) => c.value === activeCategory.value) ?? null);
const categoryFiles = computed(() => {
    if (!activeCategory.value) return [];
    return byCategory.value[activeCategory.value] || [];
});

const driveRootFolders = computed(() => props.categories.map((cat) => {
    const items = byCategory.value[cat.value] || [];
    const files = items.filter((f) => !f.is_folder);
    const rootFolders = items.filter((f) => f.is_folder && (f.parent_id ?? null) === null);
    const size = files.reduce((sum, f) => sum + (Number(f.size) || 0), 0);
    return {
        id: `cat:${cat.value}`,
        original_name: cat.label,
        is_folder: true,
        is_category: true,
        category: cat.value,
        icon: cat.icon,
        color: cat.color,
        description: cat.description || '',
        _size: size,
        _fileCount: files.length,
        _subfolderCount: rootFolders.length,
        created_at: null,
        updated_at: null,
        uploaded_by: null,
    };
}));

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

const sortByName = (a, b) => (a.original_name || '').localeCompare(b.original_name || '', 'vi');

const currentLevelFolders = computed(() => {
    if (isDriveRoot.value) return [];
    const pid = activeFolderId.value ?? null;
    return categoryFiles.value
        .filter((f) => f.is_folder && (f.parent_id ?? null) === pid)
        .slice()
        .sort(sortByName)
        .map((folder) => ({
            ...folder,
            _size: folderDescendantSize(folder.id, categoryFiles.value),
        }));
});

const activeFolderNode = computed(() => {
    if (!activeFolderId.value) return null;
    return props.attachments.find((a) => a.id === activeFolderId.value) ?? null;
});

const projectPanelTitle = computed(() => {
    if (activeFolderNode.value?.original_name) return activeFolderNode.value.original_name;
    if (activeCat.value?.label) return activeCat.value.label;
    return 'Tài liệu dự án';
});

const canMutateDocs = computed(() => props.canUpload && !isDriveRoot.value);

const folderDescendantSize = (folderId, items) => {
    const childrenByParent = new Map();
    items.forEach((item) => {
        const pid = item.parent_id ?? null;
        if (!childrenByParent.has(pid)) childrenByParent.set(pid, []);
        childrenByParent.get(pid).push(item);
    });
    let total = 0;
    const walk = (id) => {
        (childrenByParent.get(id) || []).forEach((ch) => {
            if (ch.is_folder) walk(ch.id);
            else total += Number(ch.size) || 0;
        });
    };
    walk(folderId);
    return total;
};

const taskAttachments = computed(() => {
    const rows = [];
    (props.tasks || []).forEach((task) => {
        const files = Array.isArray(task?.attachments) ? task.attachments : [];
        files.forEach((file) => {
            if (!file?.id) return;
            rows.push({
                ...file,
                task_id: task.id,
                task_title: task.title || 'Công việc',
            });
        });
    });
    return rows.sort((a, b) => {
        const ta = a.created_at ? new Date(a.created_at).getTime() : 0;
        const tb = b.created_at ? new Date(b.created_at).getTime() : 0;
        return tb - ta;
    });
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
    const pid = activeFolderId.value ?? null;
    const docs = files.filter((f) => !f.is_folder && (f.parent_id ?? null) === pid);
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

watch(activeCategory, (cat) => {
    activeFolderId.value = null;
    if (!cat) {
        selectedId.value = null;
        return;
    }
    const files = byCategory.value[cat] || [];
    const docs = files.filter((f) => !f.is_folder && (f.parent_id ?? null) === null);
    selectedId.value = firstAvailableFile(docs)?.id ?? null;
});

watch(activeFolderId, (pid) => {
    if (isDriveRoot.value) {
        selectedId.value = null;
        return;
    }
    const folderPid = pid ?? null;
    const docs = categoryFiles.value.filter((f) => !f.is_folder && (f.parent_id ?? null) === folderPid);
    if (!docs.length) {
        selectedId.value = null;
        return;
    }
    const current = docs.find((f) => f.id === selectedId.value);
    if (!current?.url) {
        selectedId.value = firstAvailableFile(docs)?.id ?? docs[0]?.id ?? null;
    }
}, { immediate: true });

const visibleFiles = computed(() => {
    if (isDriveRoot.value) return [];
    const pid = activeFolderId.value ?? null;
    return categoryFiles.value
        .filter((f) => !f.is_folder && (f.parent_id ?? null) === pid)
        .sort((a, b) => (a.original_name || '').localeCompare(b.original_name || '', 'vi'));
});

const goBackFolder = () => {
    if (activeFolderId.value) {
        activeFolderId.value = activeFolderNode.value?.parent_id ?? null;
        return;
    }
    if (activeCategory.value) {
        activeCategory.value = null;
    }
};

const goToDriveRoot = () => {
    activeFolderId.value = null;
    activeCategory.value = null;
};

const openCategoryFolder = (catValue) => {
    activeCategory.value = catValue;
    activeFolderId.value = null;
};

const openTaskAttachment = (file) => {
    if (file?.url) {
        window.open(file.url, '_blank', 'noopener,noreferrer');
        return;
    }
    if (file?.task_id != null) {
        emit('open-task', { id: file.task_id, panelTab: 'links' });
    }
};

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
    return `${n.toFixed(i ? 2 : 0)} ${units[i]}`;
};

/** «16:48 03/12/2024» */
const formatCardDate = (iso) => {
    if (!iso) return displayOrEmpty(null, 'Chưa cập nhật');
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return displayOrEmpty(null, 'Chưa cập nhật');
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(d.getHours())}:${pad(d.getMinutes())} ${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
};

/** Meta dòng thẻ: «16:48 03/12/2024 440.45 KB» */
const formatCardMeta = (iso, sizeLabel) => {
    const datePart = formatCardDate(iso);
    if (!sizeLabel) return datePart;
    if (datePart === 'Chưa cập nhật') return sizeLabel;
    return `${datePart} ${sizeLabel}`;
};

const toggleRowSelection = (id) => {
    const key = String(id);
    if (selectedRowIds.value.map(String).includes(key)) {
        selectedRowIds.value = selectedRowIds.value.filter((x) => String(x) !== key);
    } else {
        selectedRowIds.value = [...selectedRowIds.value, id];
    }
};

const toggleAllRows = (checked) => {
    if (!checked) {
        selectedRowIds.value = [];
        return;
    }
    selectedRowIds.value = [
        ...currentLevelFolders.value.map((f) => f.id),
        ...visibleFiles.value.map((f) => f.id),
    ];
};

watch([activeCategory, activeFolderId], () => {
    selectedRowIds.value = [];
});

const folderCardMeta = (folder) => {
    if (folder.is_category) {
        return formatCardMeta(null, formatSize(folder._size || 0));
    }
    const size = folderDescendantSize(folder.id, categoryFiles.value);
    return formatCardMeta(folder.updated_at || folder.created_at, formatSize(size));
};

const folderCardStats = (folder) => {
    if (folder.is_category) {
        return { files: folder._fileCount || 0, subfolders: folder._subfolderCount || 0 };
    }
    return countFolderContents(folder.id, categoryFiles.value);
};

const hasBrowsableContent = computed(() => {
    if (isDriveRoot.value) return driveRootFolders.value.length > 0;
    return currentLevelFolders.value.length > 0 || visibleFiles.value.length > 0;
});

const fileCardMeta = (file) => formatCardMeta(
    file.updated_at || file.created_at,
    formatSize(file.size, file),
);

const folderBreadcrumb = computed(() => {
    if (isDriveRoot.value) return [];
    const parts = [];
    if (activeCat.value) {
        parts.push({ id: `cat:${activeCat.value.value}`, name: activeCat.value.label, kind: 'category' });
    }
    let pid = activeFolderId.value;
    const folderParts = [];
    while (pid) {
        const node = props.attachments.find((a) => a.id === pid);
        if (!node) break;
        folderParts.unshift({ id: node.id, name: node.original_name, kind: 'folder' });
        pid = node.parent_id ?? null;
    }
    return [...parts, ...folderParts];
});

const onBreadcrumbClick = (crumb) => {
    if (crumb.kind === 'category') {
        activeFolderId.value = null;
        return;
    }
    activeFolderId.value = crumb.id;
};

const openFileDetails = (file) => {
    if (!file || file.is_folder) return;
    selectFile(file);
    detailDrawerOpen.value = true;
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
    if (!files.length || !props.canUpload || !category) return;
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
    if (!props.canUpload || isDriveRoot.value) return;
    dragging.value = true;
};

const onDragLeave = (event) => {
    const related = event.relatedTarget;
    if (related && event.currentTarget?.contains?.(related)) return;
    dragging.value = false;
};

const onDrop = (category, event) => {
    dragging.value = false;
    if (!category) {
        toast.error('Hãy mở một thư mục danh mục trước khi tải lên.');
        return;
    }
    uploadFiles(category, event.dataTransfer?.files);
};

const onDriveDrop = (event) => {
    if (isDriveRoot.value) {
        dragging.value = false;
        toast.error('Hãy mở một thư mục danh mục trước khi tải lên.');
        return;
    }
    onDrop(activeCategory.value, event);
};

const selectedNewFileType = computed(() => (
    NEW_FILE_TYPES.find((t) => t.value === newFileForm.file_type) || NEW_FILE_TYPES[0]
));

const openAddLinkModal = async () => {
    if (!activeCategory.value) {
        toast.error('Hãy mở một thư mục danh mục trước khi thêm link.');
        return;
    }
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
    activeFolderId.value = file.parent_id ?? null;
};

const openPreviewModal = (file) => {
    if (file?.is_folder) return;
    selectFile(file);
    previewEditing.value = false;
    previewDraft.value = '';
    previewModalOpen.value = true;
};

const closePreviewModal = () => {
    previewModalOpen.value = false;
    previewEditing.value = false;
    previewDraft.value = '';
};

const openDetailsFromPreview = () => {
    previewModalOpen.value = false;
    detailDrawerOpen.value = true;
};

const deleteFromPreview = () => {
    if (!selected.value) return;
    removeFile(selected.value);
    previewModalOpen.value = false;
};

const canEditSelectedContent = computed(() => (
    Boolean(props.canEdit && selected.value?.can_edit_content)
));

const startPreviewEdit = () => {
    if (!canEditSelectedContent.value) return;
    previewEditing.value = true;
};

const cancelPreviewEdit = () => {
    previewEditing.value = false;
    previewPaneRef.value?.reload?.();
};

const savePreviewContent = () => {
    if (!selected.value || !canEditSelectedContent.value) return;
    contentSaving.value = true;
    router.put(`/projects/${props.projectId}/attachments/${selected.value.id}`, {
        content: previewDraft.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            previewEditing.value = false;
            toast.success('Đã lưu nội dung.');
            nextTick(() => previewPaneRef.value?.reload?.());
        },
        onError: () => toast.error('Không thể lưu nội dung file.'),
        onFinish: () => { contentSaving.value = false; },
    });
};

const renameDisplayBase = (item) => {
    if (!item) return '';
    if (item.is_folder || item.is_external_link) return item.original_name || '';
    const name = item.original_name || '';
    const idx = name.lastIndexOf('.');
    if (idx <= 0) return name;
    return name.slice(0, idx);
};

const openRenameModal = async (item) => {
    if (!item || item.is_category || !props.canEdit) return;
    renameTarget.value = item;
    renameForm.reset();
    renameForm.title = renameDisplayBase(item);
    renameForm.clearErrors();
    showRenameModal.value = true;
    await nextTick();
    renameNameInput.value?.focus?.();
    renameNameInput.value?.select?.();
};

const closeRenameModal = () => {
    showRenameModal.value = false;
    renameTarget.value = null;
    renameForm.reset();
    renameForm.clearErrors();
};

const submitRename = () => {
    const item = renameTarget.value;
    if (!item || !props.canEdit || !renameForm.title.trim()) return;
    renameForm.clearErrors();
    renameForm.title = renameForm.title.trim();
    renameForm.put(`/projects/${props.projectId}/attachments/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeRenameModal();
            toast.success(item.is_folder ? 'Đã đổi tên thư mục.' : 'Đã đổi tên file.');
        },
        onError: (errors) => toast.error(
            firstErrorMessage(errors, ['title'], 'Không thể đổi tên.'),
        ),
    });
};

const onSelectFolder = (folder) => {
    if (folder?.is_category && folder.category) {
        openCategoryFolder(folder.category);
        return;
    }
    activeFolderId.value = folder.id;
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
    if (!activeCategory.value) {
        toast.error('Hãy mở một thư mục danh mục trước khi tạo thư mục.');
        return;
    }
    folderForm.reset();
    folderForm.category = activeCategory.value;
    folderForm.parent_id = parentId !== undefined ? parentId : activeFolderId.value;
    folderForm.is_folder = true;
    folderForm.clearErrors();
    showFolderModal.value = true;
    focusFolderInput();
};

const closeFolderModal = () => {
    showFolderModal.value = false;
    folderForm.reset();
    folderForm.clearErrors();
};

const openFileModal = (parentId = undefined) => {
    if (!activeCategory.value) {
        toast.error('Hãy mở một thư mục danh mục trước khi tạo file.');
        return;
    }
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
        activeFolderId.value = parentId;
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
    file_renamed: 'edit',
    content_updated: 'edit',
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
    file_renamed: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    content_updated: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
}[event] || 'bg-slate-100 text-slate-500 dark:bg-slate-800');
</script>

<template>
  <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden bg-slate-100/80 dark:bg-slate-950">
    <!-- Hai panel: Tài liệu dự án | Đính kèm công việc -->
    <div
      class="grid min-h-0 flex-1 gap-2.5 overflow-hidden p-2.5 max-lg:auto-rows-fr max-lg:grid-rows-[minmax(0,1.25fr)_minmax(200px,0.7fr)] lg:grid-cols-[minmax(0,1fr)_minmax(200px,248px)] lg:grid-rows-1"
    >
      <!-- Trái: Tài liệu dự án (Drive: danh mục → thư mục → file) -->
      <section
        class="relative flex min-h-0 min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
        aria-label="Tài liệu dự án"
        @dragover.prevent="onDragOver"
        @dragleave="onDragLeave"
        @drop.prevent="onDriveDrop($event)"
      >
        <div
          class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-sky-50/80 to-transparent dark:from-sky-950/30"
          aria-hidden="true"
        />
        <div
          v-if="canMutateDocs && dragging"
          class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-brand/10 backdrop-blur-[1px]"
        >
          <div class="flex flex-col items-center gap-1.5 rounded-xl border-2 border-dashed border-brand bg-white/95 px-6 py-5 shadow-sm dark:bg-slate-900/95">
            <AppIcon
              name="upload"
              :size="24"
              class="text-brand"
            />
            <p class="text-sm font-semibold text-brand">
              Thả để tải lên
            </p>
          </div>
        </div>

        <header class="relative flex shrink-0 flex-col gap-1 border-b border-slate-100/90 px-3 py-2.5 dark:border-slate-800">
          <div class="flex min-w-0 flex-wrap items-center gap-2">
            <button
              v-if="!isDriveRoot"
              type="button"
              class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
              aria-label="Quay lại"
              @click="goBackFolder"
            >
              <AppIcon
                name="chevron-left"
                :size="18"
              />
            </button>
            <div class="min-w-0 flex-1">
              <h2 class="truncate text-[15px] font-bold tracking-tight text-slate-800 dark:text-slate-100 sm:text-base">
                {{ projectPanelTitle }}
              </h2>
            </div>

            <div class="ml-auto flex shrink-0 items-center gap-1.5">
              <template v-if="canMutateDocs">
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
              <div
                class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50/80 p-0.5 dark:border-slate-600 dark:bg-slate-900"
                role="group"
                aria-label="Chế độ hiển thị"
              >
                <button
                  type="button"
                  class="grid h-7 w-7 place-items-center rounded-md transition"
                  :class="docsViewMode === 'list'
                    ? 'bg-white text-brand shadow-sm dark:bg-slate-800'
                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                  title="Hiển thị dạng danh sách"
                  :aria-pressed="docsViewMode === 'list'"
                  @click="setDocsViewMode('list')"
                >
                  <AppIcon
                    name="table"
                    :size="14"
                  />
                </button>
                <button
                  type="button"
                  class="grid h-7 w-7 place-items-center rounded-md transition"
                  :class="docsViewMode === 'grid'
                    ? 'bg-white text-brand shadow-sm dark:bg-slate-800'
                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'"
                  title="Hiển thị dạng lưới"
                  :aria-pressed="docsViewMode === 'grid'"
                  @click="setDocsViewMode('grid')"
                >
                  <AppIcon
                    name="grid"
                    :size="14"
                  />
                </button>
              </div>
              <button
                type="button"
                class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                :aria-label="leftPanelCollapsed ? 'Mở rộng panel' : 'Thu gọn panel'"
                :aria-expanded="!leftPanelCollapsed"
                @click="leftPanelCollapsed = !leftPanelCollapsed"
              >
                <AppIcon
                  name="minus"
                  :size="14"
                />
              </button>
            </div>
          </div>
          <nav
            class="flex min-w-0 items-center gap-1 overflow-x-auto text-[11px] text-slate-500"
            aria-label="Đường dẫn thư mục"
          >
            <button
              type="button"
              class="shrink-0 rounded px-1 py-0.5 font-medium transition hover:bg-slate-100 hover:text-brand dark:hover:bg-slate-800"
              :class="isDriveRoot ? 'text-slate-800 dark:text-slate-100' : 'text-slate-500'"
              @click="goToDriveRoot"
            >
              Tài liệu dự án
            </button>
            <template
              v-for="(crumb, index) in folderBreadcrumb"
              :key="crumb.id"
            >
              <AppIcon
                name="chevron-right"
                :size="10"
                class="shrink-0 opacity-50"
              />
              <button
                type="button"
                class="max-w-[12rem] truncate rounded px-1 py-0.5 font-medium hover:bg-slate-100 hover:text-brand dark:hover:bg-slate-800"
                :class="index === folderBreadcrumb.length - 1
                  ? 'text-slate-800 dark:text-slate-100'
                  : 'text-slate-500'"
                @click="onBreadcrumbClick(crumb)"
              >
                {{ crumb.name }}
              </button>
            </template>
          </nav>
        </header>

        <div
          v-show="!leftPanelCollapsed"
          class="relative min-h-0 flex-1 overflow-y-auto px-3 py-3"
        >
          <template v-if="hasBrowsableContent">
            <!-- List view -->
            <div
              v-if="docsViewMode === 'list'"
              class="min-w-0"
            >
              <DocumentFilesTable
                :folders="isDriveRoot ? driveRootFolders : currentLevelFolders"
                :files="isDriveRoot ? [] : visibleFiles"
                :selected-id="selectedId"
                :selected-ids="selectedRowIds"
                :format-size="formatSize"
                :format-date="formatCardDate"
                :file-ext="fileExt"
                :can-edit="canEdit && !isDriveRoot"
                :can-delete="canDelete && !isDriveRoot"
                @select-folder="onSelectFolder"
                @select-file="openPreviewModal"
                @toggle-row="toggleRowSelection"
                @toggle-all="toggleAllRows"
                @rename-item="openRenameModal"
                @preview-file="openPreviewModal"
                @delete-item="removeFile"
              />
            </div>

            <!-- Grid view -->
            <template v-else>
              <div
                v-if="isDriveRoot"
                class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
              >
                <DocumentFolderCard
                  v-for="folder in driveRootFolders"
                  :key="folder.id"
                  :name="folder.original_name"
                  :meta="formatSize(folder._size || 0)"
                  :icon="folder.icon || 'folder'"
                  :file-count="folderCardStats(folder).files"
                  :subfolder-count="folderCardStats(folder).subfolders"
                  @click="onSelectFolder(folder)"
                />
              </div>

              <template v-else>
                <div v-if="currentLevelFolders.length">
                  <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    <DocumentFolderCard
                      v-for="folder in currentLevelFolders"
                      :key="folder.id"
                      :name="folder.original_name"
                      :meta="folderCardMeta(folder)"
                      :file-count="folderCardStats(folder).files"
                      :subfolder-count="folderCardStats(folder).subfolders"
                      :can-rename="canEdit"
                      :can-delete="canDelete"
                      @click="onSelectFolder(folder)"
                      @rename="openRenameModal(folder)"
                      @delete="removeFile(folder)"
                    />
                  </div>
                </div>

                <div
                  v-if="visibleFiles.length"
                  :class="currentLevelFolders.length ? 'mt-5' : ''"
                >
                  <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    <DocumentFileCard
                      v-for="file in visibleFiles"
                      :key="file.id"
                      :name="file.original_name"
                      :meta="fileCardMeta(file)"
                      :url="file.url"
                      :is-image="Boolean(file.is_image)"
                      :is-pdf="Boolean(file.is_pdf)"
                      :is-link="Boolean(file.is_external_link)"
                      :badge="listBadge(file)"
                      :active="selectedId === file.id"
                      :can-delete="canDelete"
                      @click="openPreviewModal(file)"
                      @preview="openPreviewModal(file)"
                      @details="openFileDetails(file)"
                      @delete="removeFile(file)"
                    />
                  </div>
                </div>
              </template>
            </template>
          </template>

          <div
            v-else
            class="flex min-h-[12rem] flex-col items-center justify-center rounded-xl border border-dashed border-slate-200/90 bg-slate-50/60 px-4 py-8 text-center dark:border-slate-700 dark:bg-slate-950/40"
          >
            <span class="grid h-12 w-12 place-items-center rounded-xl bg-amber-50 ring-1 ring-amber-200/70 dark:bg-amber-950/40 dark:ring-amber-800/40">
              <AppIcon
                :name="activeFolderId ? 'folder-open' : 'folder'"
                :size="22"
                class="text-amber-500"
              />
            </span>
            <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
              {{ activeFolderId ? 'Thư mục trống' : 'Chưa có tài liệu' }}
            </p>
            <div
              v-if="canMutateDocs"
              class="mt-3 flex flex-wrap items-center justify-center gap-2"
            >
              <button
                type="button"
                class="btn-ghost inline-flex h-8 items-center gap-1.5 border border-slate-200 px-2.5 text-xs dark:border-slate-600"
                @click="openFileModal()"
              >
                <AppIcon
                  name="documents"
                  :size="13"
                />
                Tạo file
              </button>
              <button
                type="button"
                class="btn-primary inline-flex h-8 items-center gap-1.5 px-2.5 text-xs"
                @click="openFolderModal(activeFolderId || undefined)"
              >
                <AppIcon
                  name="plus"
                  :size="13"
                />
                Tạo thư mục
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Phải: Đính kèm công việc (cột hẹp) -->
      <section
        class="relative flex min-h-0 min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
        aria-label="Đính kèm công việc"
      >
        <header class="relative flex shrink-0 items-center gap-1.5 border-b border-slate-100/90 px-2.5 py-2.5 dark:border-slate-800">
          <div class="min-w-0 flex-1">
            <h2 class="truncate text-sm font-bold tracking-tight text-slate-800 dark:text-slate-100">
              Đính kèm công việc
            </h2>
          </div>
          <span
            class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums"
            :class="taskAttachments.length
              ? 'bg-brand/10 text-brand'
              : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500'"
          >
            {{ taskAttachments.length }}
          </span>
          <button
            type="button"
            class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
            :aria-label="rightPanelCollapsed ? 'Mở rộng panel' : 'Thu gọn panel'"
            :aria-expanded="!rightPanelCollapsed"
            @click="rightPanelCollapsed = !rightPanelCollapsed"
          >
            <AppIcon
              name="minus"
              :size="13"
            />
          </button>
        </header>

        <div
          v-show="!rightPanelCollapsed"
          class="relative min-h-0 flex-1 overflow-y-auto px-2 py-2"
        >
          <template v-if="taskAttachments.length">
            <ul
              class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200/80 dark:divide-slate-800 dark:border-slate-700"
              role="list"
            >
              <li
                v-for="file in taskAttachments"
                :key="`task-att-${file.id}`"
              >
                <button
                  type="button"
                  class="flex w-full min-w-0 items-start gap-2 px-2 py-2 text-left transition hover:bg-slate-50 dark:hover:bg-slate-800/60"
                  :title="file.task_title ? `${file.original_name} · ${file.task_title}` : file.original_name"
                  @click="openTaskAttachment(file)"
                >
                  <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-md bg-slate-50 ring-1 ring-slate-200/70 dark:bg-slate-800 dark:ring-slate-700">
                    <AppIcon
                      :name="file.is_image ? 'image' : (file.is_pdf ? 'pdf' : 'documents')"
                      :size="13"
                      :class="file.is_pdf ? 'text-rose-500' : (file.is_image ? 'text-violet-500' : 'text-sky-500')"
                    />
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block break-words text-[12px] font-medium leading-snug text-slate-800 dark:text-slate-100">
                      {{ file.original_name }}
                    </span>
                    <span class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[10px] tabular-nums text-slate-400">
                      <span>{{ formatSize(file.size, file) }}</span>
                      <span
                        v-if="file.task_title"
                        class="min-w-0 break-words"
                      >· {{ file.task_title }}</span>
                    </span>
                  </span>
                </button>
              </li>
            </ul>
          </template>
          <div
            v-else
            class="flex min-h-[10rem] flex-col items-center justify-center rounded-xl border border-dashed border-slate-200/90 bg-slate-50/60 px-3 py-6 text-center dark:border-slate-700 dark:bg-slate-950/40"
          >
            <AppIcon
              name="task"
              :size="22"
              class="text-brand/60"
            />
            <p class="mt-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
              Chưa có đính kèm
            </p>
          </div>
        </div>
      </section>
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

    <!-- Preview full-screen -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-100 ease-in"
        leave-to-class="opacity-0"
      >
        <div
          v-if="previewModalOpen"
          class="fixed inset-0 z-50 flex flex-col bg-slate-900/60"
          role="dialog"
          aria-modal="true"
          tabindex="-1"
          @click.self="closePreviewModal"
          @keydown.esc="closePreviewModal"
        >
          <div class="flex min-h-0 flex-1 flex-col bg-white dark:bg-slate-900">
            <header
              class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900"
            >
              <div class="min-w-0 flex-1">
                <h2 class="truncate font-display text-base font-semibold text-slate-800 dark:text-slate-100">
                  {{ selected?.original_name ?? 'Tài liệu' }}
                </h2>
              </div>
              <div class="flex shrink-0 items-center gap-1.5">
                <template v-if="canEditSelectedContent">
                  <button
                    v-if="!previewEditing"
                    type="button"
                    class="btn-ghost inline-flex h-8 items-center gap-1 px-2.5 text-xs"
                    @click="startPreviewEdit"
                  >
                    <AppIcon
                      name="edit"
                      :size="13"
                    />
                    Chỉnh sửa
                  </button>
                  <template v-else>
                    <button
                      type="button"
                      class="btn-ghost h-8 px-2.5 text-xs"
                      :disabled="contentSaving"
                      @click="cancelPreviewEdit"
                    >
                      Huỷ
                    </button>
                    <button
                      type="button"
                      class="btn-primary inline-flex h-8 items-center gap-1 px-2.5 text-xs"
                      :disabled="contentSaving"
                      @click="savePreviewContent"
                    >
                      <AppIcon
                        :name="contentSaving ? 'refresh' : 'check'"
                        :size="13"
                        :class="contentSaving ? 'animate-spin' : ''"
                      />
                      {{ contentSaving ? 'Đang lưu…' : 'Lưu' }}
                    </button>
                  </template>
                </template>
                <button
                  v-if="canEdit && selected && !previewEditing && !canEditSelectedContent"
                  type="button"
                  class="btn-ghost grid h-8 w-8 place-items-center p-0"
                  title="Đổi tên"
                  @click="openRenameModal(selected)"
                >
                  <AppIcon
                    name="edit"
                    :size="14"
                  />
                </button>
                <a
                  v-if="selected?.url"
                  :href="selected.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn-ghost grid h-8 w-8 place-items-center p-0"
                  title="Mở tab mới"
                >
                  <AppIcon
                    name="eye"
                    :size="14"
                  />
                </a>
                <a
                  v-if="selected && !selected.is_external_link && selected.url"
                  :href="selected.url"
                  download
                  class="btn-ghost grid h-8 w-8 place-items-center p-0"
                  title="Tải xuống"
                >
                  <AppIcon
                    name="download"
                    :size="14"
                  />
                </a>
                <button
                  v-if="selected"
                  type="button"
                  class="btn-ghost grid h-8 w-8 place-items-center p-0"
                  title="Chi tiết"
                  @click="openDetailsFromPreview"
                >
                  <AppIcon
                    name="info"
                    :size="14"
                  />
                </button>
                <button
                  v-if="canDelete && selected"
                  type="button"
                  class="btn-ghost grid h-8 w-8 place-items-center p-0 text-rose-500"
                  title="Xoá"
                  @click="deleteFromPreview"
                >
                  <AppIcon
                    name="delete"
                    :size="14"
                  />
                </button>
                <button
                  type="button"
                  class="grid h-8 w-8 place-items-center rounded-btn text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                  aria-label="Đóng"
                  @click="closePreviewModal"
                >
                  <AppIcon
                    name="close"
                    :size="16"
                  />
                </button>
              </div>
            </header>

            <div class="min-h-0 flex-1 overflow-hidden">
              <DocumentPreviewPane
                ref="previewPaneRef"
                class="h-full"
                :file="selected"
                :editing="previewEditing"
                :can-edit="canEditSelectedContent"
                @update:draft="previewDraft = $event"
              />
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

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

    <Modal
      ref="renameModalRef"
      :show="showRenameModal"
      :title="renameTarget?.is_folder ? 'Đổi tên thư mục' : 'Đổi tên file'"
      max-width="max-w-sm"
      :dirty="Boolean(renameForm.title)"
      @close="closeRenameModal"
    >
      <div>
        <label
          for="doc-rename-title"
          class="text-xs font-medium text-slate-500"
        >Tên mới</label>
        <input
          id="doc-rename-title"
          ref="renameNameInput"
          v-model="renameForm.title"
          type="text"
          class="input mt-1 h-9 w-full text-sm"
          placeholder="Nhập tên"
          maxlength="255"
          autocomplete="off"
          @keyup.enter="submitRename"
        >
        <p
          v-if="renameForm.errors.title"
          class="mt-1 text-xs text-rose-600"
        >
          {{ renameForm.errors.title }}
        </p>
      </div>
      <div class="mt-3 flex justify-end gap-2">
        <button
          type="button"
          class="btn-ghost h-8 text-xs"
          @click="closeRenameModal"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary inline-flex h-8 items-center gap-1 text-xs"
          :disabled="renameForm.processing || !renameForm.title.trim()"
          @click="submitRename"
        >
          <AppIcon
            name="edit"
            :size="13"
          />
          {{ renameForm.processing ? 'Đang lưu…' : 'Đổi tên' }}
        </button>
      </div>
    </Modal>
  </div>
</template>

