<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { datetime } from '@/composables/useFormat';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { useToast } from '@/shared/composables/useToast';
import DocumentPreviewPane from '@/modules/project/components/Documents/DocumentPreviewPane.vue';

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
const fileInputs = ref({});
const replaceInput = ref(null);
const editingNotes = ref(false);

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

watch(categoryFiles, (files) => {
    if (!files.length) {
        selectedId.value = null;
        return;
    }
    if (!files.some((f) => f.id === selectedId.value)) {
        selectedId.value = files[0].id;
    }
}, { immediate: true });

watch(selected, (file) => {
    notesForm.notes = file?.notes ?? '';
    editingNotes.value = false;
    notesForm.clearErrors();
}, { immediate: true });

watch(activeCategory, () => {
    const files = byCategory.value[activeCategory.value] || [];
    selectedId.value = files[0]?.id ?? null;
});

const totalCount = computed(() => props.attachments.length);

const formatSize = (bytes) => {
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

const onFilesSelected = (category, event) => {
    const files = [...(event.target.files || [])];
    if (!files.length) return;
    uploadingCategory.value = category;
    router.post(`/projects/${props.projectId}/attachments`, { category, files }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => toast.success('Đã tải tài liệu lên'),
        onError: () => toast.error('Không thể tải lên. Kiểm tra định dạng và dung lượng file.'),
        onFinish: () => {
            uploadingCategory.value = null;
            event.target.value = '';
        },
    });
};

const selectFile = (file) => { selectedId.value = file.id; };

const removeFile = (file) => {
    confirmDelete(
        `Xoá "${file.original_name}"?`,
        () => router.delete(`/projects/${props.projectId}/attachments/${file.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã xoá tài liệu'),
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
            toast.success('Đã lưu ghi chú');
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
        onSuccess: () => toast.success('Đã thay thế file'),
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
    if (file?.is_pdf) return 'PDF';
    if (file?.mime_type) {
        const short = file.mime_type.split('/').pop();
        return short.length <= 12 ? short.toUpperCase() : ext.toUpperCase() || 'Tài liệu';
    }
    return ext.toUpperCase() || '—';
};

const activityTone = (event) => ({
    uploaded: 'bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-400',
    note_updated: 'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400',
    replaced: 'bg-violet-100 text-violet-600 dark:bg-violet-950 dark:text-violet-400',
    deleted: 'bg-rose-100 text-rose-600 dark:bg-rose-950 dark:text-rose-400',
}[event] || 'bg-slate-100 text-slate-500 dark:bg-slate-800');
</script>

<template>
  <div class="flex h-full flex-col overflow-hidden">
    <!-- Header -->
    <div class="shrink-0 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-900">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="font-display text-lg font-semibold text-slate-800 dark:text-slate-100">
            Tài liệu dự án
          </h2>
          <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
            Chọn tab danh mục → xem preview & lịch sử chỉnh sửa từng file.
          </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800">
          <span class="text-slate-500">Tổng:</span>
          <span class="ml-1 font-semibold text-brand">{{ totalCount }}</span>
        </div>
      </div>

      <!-- Category tabs -->
      <nav class="mt-4 flex gap-1 overflow-x-auto border-b border-slate-100 dark:border-slate-700">
        <button
          v-for="cat in categories"
          :key="cat.value"
          type="button"
          class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition"
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

    <!-- Category toolbar -->
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/80 px-5 py-2.5 dark:border-slate-700 dark:bg-slate-900/50">
      <p class="text-xs text-slate-500 dark:text-slate-400">
        {{ activeCat?.description }}
      </p>
      <button
        v-if="canUpload"
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
    </div>

    <!-- Main split: list | preview + audit -->
    <div class="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[280px_1fr]">
      <!-- File list -->
      <div class="min-h-0 overflow-y-auto border-b border-slate-200 bg-white lg:border-b-0 lg:border-r dark:border-slate-700 dark:bg-slate-900">
        <div
          v-if="!categoryFiles.length"
          class="flex flex-col items-center justify-center px-4 py-16 text-center"
        >
          <AppIcon
            :name="activeCat?.icon || 'documents'"
            :size="32"
            class="text-slate-300"
          />
          <p class="mt-2 text-sm text-slate-400">
            Chưa có tài liệu.
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
                :class="file.is_image ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-500'"
              >
                <img
                  v-if="file.is_image"
                  :src="file.url"
                  :alt="file.original_name"
                  class="h-9 w-9 rounded-lg object-cover"
                >
                <span v-else>{{ fileExt(file.original_name) }}</span>
              </span>
              <span class="min-w-0 flex-1">
                <span class="line-clamp-2 text-sm font-medium text-slate-800 dark:text-slate-100">{{ file.original_name }}</span>
                <span class="mt-0.5 block text-[10px] text-slate-400">
                  {{ formatSize(file.size) }}
                  <span v-if="file.uploaded_by?.name"> · {{ file.uploaded_by.name.split(' ').pop() }}</span>
                </span>
              </span>
            </button>
          </li>
        </ul>
      </div>

      <!-- Preview + audit -->
      <div class="flex min-h-0 flex-col overflow-hidden bg-slate-50 dark:bg-slate-950">
        <template v-if="selected">
          <!-- Preview -->
          <div class="min-h-0 flex-1 overflow-hidden p-4">
            <div class="card flex h-full min-h-[240px] flex-col overflow-hidden dark:border-slate-700 dark:bg-slate-900">
              <div class="flex shrink-0 items-center justify-between gap-2 border-b border-slate-100 px-4 py-2.5 dark:border-slate-700">
                <h3 class="min-w-0 truncate font-medium text-slate-800 dark:text-slate-100">
                  {{ selected.original_name }}
                </h3>
                <div class="flex shrink-0 items-center gap-1">
                  <a
                    :href="selected.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="btn-ghost grid h-8 w-8 place-items-center p-0"
                    title="Mở tab mới"
                  >
                    <AppIcon
                      name="eye"
                      :size="15"
                    />
                  </a>
                  <a
                    :href="selected.url"
                    download
                    class="btn-ghost grid h-8 w-8 place-items-center p-0"
                    title="Tải xuống"
                  >
                    <AppIcon
                      name="download"
                      :size="15"
                    />
                  </a>
                  <button
                    v-if="canDelete"
                    type="button"
                    class="btn-ghost grid h-8 w-8 place-items-center p-0 text-rose-500"
                    title="Xoá"
                    @click="removeFile(selected)"
                  >
                    <AppIcon
                      name="delete"
                      :size="15"
                    />
                  </button>
                </div>
              </div>
              <div class="min-h-0 flex-1 overflow-auto bg-slate-100/50 p-3 dark:bg-slate-950">
                <DocumentPreviewPane :file="selected" />
              </div>
            </div>
          </div>

          <!-- Meta + notes + audit -->
          <div class="doc-meta-panel shrink-0 max-h-[48%] overflow-y-auto border-t border-slate-200 bg-slate-50/60 dark:border-slate-700 dark:bg-slate-900/80">
            <div class="grid gap-3 p-3 sm:p-4 lg:grid-cols-2 lg:gap-4">
              <!-- Cột trái: thông tin + ghi chú -->
              <div class="flex flex-col gap-3">
                <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                  <header class="border-b border-slate-100 bg-slate-50/90 px-3.5 py-2 dark:border-slate-800 dark:bg-slate-800/50">
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                      Thông tin file
                    </h4>
                  </header>
                  <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <li class="flex flex-col gap-1 px-3.5 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                      <span class="shrink-0 text-xs font-medium text-slate-500">Người tải lên</span>
                      <span class="flex min-w-0 items-center gap-2 font-medium text-slate-800 dark:text-slate-100">
                        <Avatar
                          v-if="selected.uploaded_by"
                          :name="selected.uploaded_by.name"
                          :size="24"
                        />
                        <span class="truncate">{{ selected.uploaded_by?.name ?? '—' }}</span>
                      </span>
                    </li>
                    <li class="flex flex-col gap-0.5 px-3.5 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                      <span class="text-xs font-medium text-slate-500">Ngày tải</span>
                      <span class="text-slate-800 dark:text-slate-200">{{ datetime(selected.created_at) }}</span>
                    </li>
                    <li class="flex flex-col gap-0.5 px-3.5 py-2.5 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                      <span class="shrink-0 text-xs font-medium text-slate-500">Cập nhật</span>
                      <span class="min-w-0 text-right text-slate-800 dark:text-slate-200 sm:text-left">
                        {{ datetime(selected.updated_at) }}
                        <span
                          v-if="selected.updated_by?.name"
                          class="mt-0.5 block text-xs font-normal text-slate-400"
                        >bởi {{ selected.updated_by.name }}</span>
                      </span>
                    </li>
                    <li class="flex items-center justify-between gap-4 px-3.5 py-2.5">
                      <span class="text-xs font-medium text-slate-500">Dung lượng</span>
                      <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        {{ formatSize(selected.size) }}
                      </span>
                    </li>
                    <li class="flex items-center justify-between gap-4 px-3.5 py-2.5">
                      <span class="text-xs font-medium text-slate-500">Định dạng</span>
                      <span
                        class="max-w-[60%] truncate rounded-md bg-brand/10 px-2 py-0.5 text-xs font-semibold text-brand"
                        :title="selected.mime_type"
                      >{{ formatFileType(selected) }}</span>
                    </li>
                  </ul>
                </section>

                <section class="flex flex-1 flex-col overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                  <header class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/90 px-3.5 py-2 dark:border-slate-800 dark:bg-slate-800/50">
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                      Ghi chú
                    </h4>
                    <button
                      v-if="canEdit && !editingNotes"
                      type="button"
                      class="rounded-md px-2 py-0.5 text-xs font-medium text-brand transition hover:bg-brand/10"
                      @click="editingNotes = true"
                    >
                      Chỉnh sửa
                    </button>
                  </header>
                  <div class="flex min-h-[88px] flex-1 flex-col px-3.5 py-2.5">
                    <textarea
                      v-if="editingNotes && canEdit"
                      v-model="notesForm.notes"
                      rows="3"
                      class="input min-h-[72px] w-full flex-1 resize-y text-sm"
                      placeholder="Mô tả phiên bản, nguồn tài liệu, ghi chú nội bộ…"
                    />
                    <p
                      v-else
                      class="flex-1 whitespace-pre-wrap text-sm leading-relaxed"
                      :class="selected.notes ? 'text-slate-700 dark:text-slate-200' : 'italic text-slate-400'"
                    >
                      {{ selected.notes || 'Chưa có ghi chú.' }}
                    </p>
                    <div
                      v-if="editingNotes && canEdit"
                      class="mt-2 flex justify-end gap-2 border-t border-slate-100 pt-2 dark:border-slate-800"
                    >
                      <button
                        type="button"
                        class="btn-ghost text-xs"
                        @click="editingNotes = false; notesForm.notes = selected.notes ?? ''"
                      >
                        Huỷ
                      </button>
                      <button
                        type="button"
                        class="btn-primary text-xs"
                        :disabled="notesForm.processing"
                        @click="saveNotes"
                      >
                        Lưu
                      </button>
                    </div>
                  </div>
                  <footer
                    v-if="canDelete"
                    class="border-t border-slate-100 bg-slate-50/50 px-3.5 py-2 dark:border-slate-800 dark:bg-slate-800/30"
                  >
                    <button
                      type="button"
                      class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-600 transition hover:border-brand/40 hover:bg-brand/5 hover:text-brand dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-brand/50"
                      @click="pickReplace"
                    >
                      <AppIcon
                        name="refresh"
                        :size="14"
                      />
                      Thay thế file
                    </button>
                    <input
                      ref="replaceInput"
                      type="file"
                      class="hidden"
                      :accept="acceptFor(activeCategory)"
                      @change="onReplaceSelected"
                    >
                  </footer>
                </section>
              </div>

              <!-- Cột phải: audit -->
              <section class="flex min-h-[200px] flex-col overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:min-h-0">
                <header class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/90 px-3.5 py-2 dark:border-slate-800 dark:bg-slate-800/50">
                  <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Lịch sử chỉnh sửa
                  </h4>
                  <span
                    v-if="activities.length"
                    class="rounded-full bg-slate-200/80 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                  >{{ activities.length }}</span>
                </header>

                <ul
                  v-if="activities.length"
                  class="doc-audit-list relative flex-1 space-y-0 overflow-y-auto px-3 py-3"
                >
                  <li
                    v-for="(item, index) in activities"
                    :key="item.id"
                    class="doc-audit-item relative flex gap-3 pb-4 pl-1 last:pb-0"
                    :class="index < activities.length - 1 ? 'doc-audit-item--line' : ''"
                  >
                    <span
                      class="relative z-[1] mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full ring-2 ring-white dark:ring-slate-900"
                      :class="activityTone(item.event)"
                    >
                      <AppIcon
                        :name="activityIcon(item.event)"
                        :size="13"
                      />
                    </span>
                    <div class="min-w-0 flex-1 pt-0.5">
                      <p class="text-sm font-medium leading-snug text-slate-800 dark:text-slate-100">
                        {{ item.description }}
                      </p>
                      <p class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11px] text-slate-400">
                        <span>{{ datetime(item.created_at) }}</span>
                        <span
                          v-if="item.employee?.name"
                          class="text-slate-300"
                        >·</span>
                        <span
                          v-if="item.employee?.name"
                          class="font-medium text-slate-500"
                        >
                          {{ item.employee.name }}
                        </span>
                      </p>
                    </div>
                  </li>
                </ul>

                <div
                  v-else
                  class="flex flex-1 flex-col items-center justify-center px-4 py-8 text-center"
                >
                  <span class="grid h-10 w-10 place-items-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800">
                    <AppIcon
                      name="report-history"
                      :size="18"
                    />
                  </span>
                  <p class="mt-2 text-sm text-slate-500">
                    Chưa có lịch sử
                  </p>
                  <p class="mt-0.5 max-w-[220px] text-xs text-slate-400">
                    Tải lên, sửa ghi chú hoặc thay file để ghi nhận audit.
                  </p>
                </div>
              </section>
            </div>
          </div>
        </template>

        <div
          v-else
          class="flex flex-1 flex-col items-center justify-center p-8 text-center text-slate-400"
        >
          <AppIcon
            name="documents"
            :size="36"
            class="opacity-40"
          />
          <p class="mt-3 text-sm">
            Chọn một tài liệu để xem preview và audit.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.doc-audit-item--line::before {
    content: '';
    position: absolute;
    left: 0.875rem;
    top: 1.75rem;
    bottom: -0.25rem;
    width: 2px;
    background: linear-gradient(to bottom, rgb(226 232 240), rgb(226 232 240 / 0));
    border-radius: 1px;
}

:global(.dark) .doc-audit-item--line::before {
    background: linear-gradient(to bottom, rgb(51 65 85), rgb(51 65 85 / 0));
}

.doc-meta-panel {
    scrollbar-gutter: stable;
}

.doc-audit-list {
    max-height: min(220px, 40vh);
}
</style>
