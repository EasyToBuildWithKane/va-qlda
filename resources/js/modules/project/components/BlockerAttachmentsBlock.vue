<script setup>
import { ref, computed, watch, onBeforeUnmount, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { useToast } from '@/shared/composables/useToast';
import { useConfirmDelete } from '@/composables/useConfirmClose';

const props = defineProps({
    /** null khi đang tạo mới — file giữ trong pendingFiles đến khi có id */
    blockerId: { type: Number, default: null },
    attachments: { type: Array, default: () => [] },
    canUpload: { type: Boolean, default: false },
    /** File chờ upload (tạo mới) — phần tử { key, file, name, isImage, preview? } */
    pendingFiles: { type: Array, default: () => [] },
    /** Giao diện gọn trong modal */
    compact: { type: Boolean, default: false },
    /** Một hàng trong form ghi nhiều vướng mắc — nút ảnh + thumbnail nhỏ */
    inline: { type: Boolean, default: false },
    /** Chờ đến khi bấm Lưu/Ghi nhận (tạo mới hoặc sửa) — không POST ngay từng lần chọn file */
    stageUntilSave: { type: Boolean, default: false },
});

const emit = defineEmits(['update:pendingFiles']);

const toast = useToast();
const confirmDelete = useConfirmDelete();
const fileInput = ref(null);
const imageInput = ref(null);
const dragging = ref(false);
const uploading = ref(false);
const pending = ref([]);

const lightboxIndex = ref(-1);
const pdfPreview = ref(null);

const available = computed(() =>
    props.attachments.filter((f) => f.file_available !== false && f.url),
);

const imageFiles = computed(() => available.value.filter((f) => f.is_image));
const otherFiles = computed(() => available.value.filter((f) => !f.is_image));
const isCreateStage = computed(() => props.canUpload && props.blockerId == null);
const shouldStageFiles = computed(() => props.stageUntilSave || isCreateStage.value);
const missingFiles = computed(() =>
    props.attachments.filter((f) => f.file_available === false || !f.url),
);

const lightboxOpen = computed(() => lightboxIndex.value >= 0 && lightboxIndex.value < imageFiles.value.length);
const lightboxItem = computed(() => (lightboxOpen.value ? imageFiles.value[lightboxIndex.value] : null));

const isPdf = (file) =>
    file?.mime_type === 'application/pdf'
    || (file?.original_name ?? '').toLowerCase().endsWith('.pdf');

const formatSize = (bytes) => {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB'];
    let n = bytes;
    let i = 0;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return `${n.toFixed(i ? 1 : 0)} ${units[i]}`;
};

const revokePending = (entry) => {
    if (entry?.preview) URL.revokeObjectURL(entry.preview);
};

const clearPending = () => {
    pending.value.forEach(revokePending);
    pending.value = [];
};

onBeforeUnmount(clearPending);

const appendStaged = (files) => {
    const next = [...props.pendingFiles];
    const maxTotal = 50;
    let added = 0;
    for (const file of files) {
        if (next.length >= maxTotal) {
            if (added === 0) {
                toast.warning(`Tối đa ${maxTotal} ảnh/file chờ tải mỗi lần lưu.`);
            }
            break;
        }
        const isImage = (file.type || '').startsWith('image/');
        next.push({
            key: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
            file,
            name: file.name,
            isImage,
            preview: isImage ? URL.createObjectURL(file) : null,
        });
        added += 1;
    }
    if (added > 0 && files.length > added) {
        toast.warning(`Chỉ thêm ${added} file (giới hạn ${maxTotal} file/lần lưu).`);
    }
    emit('update:pendingFiles', next);
};

const removeStaged = (key) => {
    const item = props.pendingFiles.find((p) => p.key === key);
    if (item?.preview) URL.revokeObjectURL(item.preview);
    emit('update:pendingFiles', props.pendingFiles.filter((p) => p.key !== key));
};

const uploadFiles = (fileList) => {
    const files = [...(fileList || [])];
    if (!files.length || !props.canUpload) return;

    if (shouldStageFiles.value) {
        appendStaged(files);
        return;
    }

    const entries = files.map((file) => ({
        key: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
        name: file.name,
        isImage: (file.type || '').startsWith('image/'),
        preview: (file.type || '').startsWith('image/') ? URL.createObjectURL(file) : null,
    }));
    pending.value.push(...entries);
    uploading.value = true;

    router.post(`/blockers/${props.blockerId}/attachments`, { files }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => toast.success('Đã tải file lên'),
        onError: () => toast.error('Không tải được file. Kiểm tra định dạng và dung lượng (tối đa 10MB/file).'),
        onFinish: () => {
            uploading.value = false;
            entries.forEach((e) => {
                const p = pending.value.find((x) => x.key === e.key);
                revokePending(p);
            });
            pending.value = pending.value.filter((p) => !entries.some((e) => e.key === p.key));
        },
    });
};

const onFilesSelected = (event) => {
    uploadFiles(event.target.files);
    event.target.value = '';
};

const onDrop = (event) => {
    dragging.value = false;
    uploadFiles(event.dataTransfer?.files);
};

const pickAny = () => fileInput.value?.click();
const pickImages = () => imageInput.value?.click();

const removeAttachment = (file) => {
    if (!props.blockerId) return;
    confirmDelete(
        `Xoá file "${file.original_name}"?`,
        () => router.delete(`/blockers/${props.blockerId}/attachments/${file.id}`, { preserveScroll: true }),
        { title: 'Xoá file đính kèm' },
    );
};

const openPreview = (file) => {
    if (file.is_image) {
        const idx = imageFiles.value.findIndex((f) => f.id === file.id);
        lightboxIndex.value = idx >= 0 ? idx : 0;
        return;
    }
    if (isPdf(file)) {
        pdfPreview.value = file;
        return;
    }
    if (file.url) window.open(file.url, '_blank', 'noopener,noreferrer');
};

const closeLightbox = () => {
    lightboxIndex.value = -1;
};

const lightboxPrev = () => {
    if (lightboxIndex.value > 0) lightboxIndex.value -= 1;
};

const lightboxNext = () => {
    if (lightboxIndex.value < imageFiles.value.length - 1) lightboxIndex.value += 1;
};

const onLightboxKey = (event) => {
    if (!lightboxOpen.value) return;
    if (event.key === 'Escape') closeLightbox();
    if (event.key === 'ArrowLeft') lightboxPrev();
    if (event.key === 'ArrowRight') lightboxNext();
};

onMounted(() => window.addEventListener('keydown', onLightboxKey));
onUnmounted(() => window.removeEventListener('keydown', onLightboxKey));

watch(imageFiles, (list) => {
    if (lightboxIndex.value >= list.length) lightboxIndex.value = list.length ? list.length - 1 : -1;
});
</script>

<template>
  <div :class="inline ? 'space-y-1' : 'space-y-3'">
    <div
      v-if="canUpload && inline"
      class="flex flex-wrap items-center gap-1.5"
    >
      <button
        type="button"
        class="inline-flex h-8 shrink-0 items-center gap-1 rounded-btn border border-slate-200 bg-white px-2 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
        :disabled="uploading"
        @click="pickImages"
      >
        <AppIcon
          name="image"
          :size="14"
        />
        Ảnh
      </button>
      <div
        v-for="item in pendingFiles"
        :key="item.key"
        class="group relative h-10 w-10 shrink-0 overflow-hidden rounded-md border border-slate-200 bg-slate-100"
      >
        <img
          v-if="item.preview"
          :src="item.preview"
          :alt="item.name"
          class="h-full w-full object-cover"
        >
        <div
          v-else
          class="flex h-full items-center justify-center text-slate-400"
        >
          <AppIcon
            name="template"
            :size="14"
          />
        </div>
        <button
          type="button"
          class="absolute inset-0 grid place-items-center bg-black/45 text-white opacity-0 transition group-hover:opacity-100"
          title="Bỏ file"
          @click="removeStaged(item.key)"
        >
          <AppIcon
            name="close"
            :size="12"
          />
        </button>
      </div>
      <span
        v-if="!pendingFiles.length"
        class="text-[11px] text-slate-400"
      >Tuỳ chọn</span>
      <input
        ref="imageInput"
        type="file"
        class="hidden"
        multiple
        accept="image/jpeg,image/png,image/gif,image/webp"
        @change="onFilesSelected"
      >
    </div>
    <div
      v-else-if="canUpload"
      class="rounded-xl border-2 border-dashed transition"
      :class="[
        dragging ? 'border-brand bg-brand/5' : 'border-slate-200 dark:border-slate-600',
        compact ? 'px-3 py-4' : 'px-4 py-6',
      ]"
      @dragover.prevent="dragging = true"
      @dragleave="dragging = false"
      @drop.prevent="onDrop"
    >
      <div class="flex flex-col items-center text-center sm:flex-row sm:justify-center sm:gap-4 sm:text-left">
        <AppIcon
          name="upload"
          :size="compact ? 22 : 28"
          class="shrink-0 text-slate-400"
        />
        <div class="mt-2 sm:mt-0">
          <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
            Kéo thả hoặc chọn nhiều ảnh
          </p>
          <p class="mt-0.5 text-xs text-slate-500">
            1 vướng mắc · nhiều ảnh được · JPG, PNG, WebP… · tối đa 10MB/ảnh
          </p>
        </div>
      </div>
      <div class="mt-3 flex flex-wrap justify-center gap-2">
        <button
          type="button"
          class="btn-primary text-xs"
          :disabled="uploading"
          @click="pickImages"
        >
          <AppIcon
            name="image"
            :size="14"
            class="mr-1 inline"
          />
          Chọn ảnh
        </button>
        <button
          type="button"
          class="inline-flex h-9 items-center rounded-btn border border-slate-200 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
          :disabled="uploading"
          @click="pickAny"
        >
          Chọn file khác
        </button>
      </div>
      <input
        ref="imageInput"
        type="file"
        class="hidden"
        multiple
        accept="image/jpeg,image/png,image/gif,image/webp"
        @change="onFilesSelected"
      >
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        multiple
        accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt"
        @change="onFilesSelected"
      >
    </div>

    <div
      v-if="shouldStageFiles && pendingFiles.length && !inline"
      class="rounded-lg border border-amber-200/90 bg-amber-50/70 p-3 ring-1 ring-amber-100"
    >
      <p class="flex items-center gap-1.5 text-xs font-semibold text-amber-950">
        <AppIcon
          name="clock"
          :size="14"
          class="text-amber-700"
        />
        Chờ lưu · {{ pendingFiles.length }} file
      </p>
      <p class="mt-0.5 text-[11px] text-amber-900/80">
        Ảnh/file sẽ được tải lên cùng lúc khi bạn bấm «Ghi nhận» hoặc «Lưu thay đổi».
      </p>
      <div class="mt-2.5 flex flex-wrap gap-2">
        <div
          v-for="item in pendingFiles"
          :key="item.key"
          class="group relative h-20 w-20 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 dark:border-slate-600"
        >
          <img
            v-if="item.preview"
            :src="item.preview"
            :alt="item.name"
            class="h-full w-full object-cover"
          >
          <div
            v-else
            class="flex h-full flex-col items-center justify-center gap-1 px-1 text-[9px] text-slate-500"
          >
            <AppIcon
              name="template"
              :size="16"
            />
            <span class="line-clamp-2 text-center">{{ item.name }}</span>
          </div>
          <button
            type="button"
            class="absolute right-0.5 top-0.5 grid h-6 w-6 place-items-center rounded-full bg-black/50 text-white opacity-0 transition group-hover:opacity-100"
            title="Bỏ file"
            @click="removeStaged(item.key)"
          >
            <AppIcon
              name="close"
              :size="12"
            />
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="pending.length"
      class="space-y-1.5"
    >
      <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
        Đang tải lên…
      </p>
      <div class="flex flex-wrap gap-2">
        <div
          v-for="item in pending"
          :key="item.key"
          class="relative h-20 w-20 overflow-hidden rounded-lg border border-slate-200 bg-slate-100 dark:border-slate-600 dark:bg-slate-800"
        >
          <img
            v-if="item.preview"
            :src="item.preview"
            :alt="item.name"
            class="h-full w-full object-cover opacity-70"
          >
          <div
            v-else
            class="flex h-full flex-col items-center justify-center gap-1 px-1 text-[9px] text-slate-500"
          >
            <AppIcon
              name="template"
              :size="16"
            />
            <span class="line-clamp-2 text-center">{{ item.name }}</span>
          </div>
          <div class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-slate-900/50">
            <AppIcon
              name="refresh"
              :size="18"
              class="animate-spin text-brand"
            />
          </div>
        </div>
      </div>
    </div>

    <div v-if="imageFiles.length">
      <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
        Ảnh ({{ imageFiles.length }}) · nhấn để phóng to
      </p>
      <div
        class="mt-2 grid gap-2"
        :class="compact ? 'grid-cols-3 sm:grid-cols-4' : 'grid-cols-2 sm:grid-cols-3'"
      >
        <div
          v-for="file in imageFiles"
          :key="file.id"
          class="group relative overflow-hidden rounded-xl border border-slate-200 bg-slate-100 dark:border-slate-600"
        >
          <button
            type="button"
            class="block w-full text-left ring-brand/30 focus:outline-none focus-visible:ring-2"
            @click="openPreview(file)"
          >
            <img
              :src="file.url"
              :alt="file.original_name"
              class="aspect-square w-full object-cover transition group-hover:scale-[1.02]"
              loading="lazy"
            >
            <span class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent px-2 py-1.5 text-[10px] font-medium text-white opacity-0 transition group-hover:opacity-100">
              {{ file.original_name }}
            </span>
          </button>
          <button
            v-if="canUpload"
            type="button"
            class="absolute right-1 top-1 grid h-7 w-7 place-items-center rounded-full bg-black/50 text-white opacity-0 transition hover:bg-rose-600 group-hover:opacity-100"
            :title="`Xoá ${file.original_name}`"
            @click="removeAttachment(file)"
          >
            <AppIcon
              name="delete"
              :size="14"
            />
          </button>
        </div>
      </div>
    </div>

    <ul
      v-if="otherFiles.length"
      class="divide-y divide-slate-100 rounded-xl border border-slate-200 dark:divide-slate-700 dark:border-slate-600"
    >
      <li
        v-for="file in otherFiles"
        :key="file.id"
        class="flex items-center gap-2 px-3 py-2"
      >
        <button
          type="button"
          class="flex min-w-0 flex-1 items-center gap-2 text-left text-sm text-slate-700 hover:text-brand dark:text-slate-200"
          @click="openPreview(file)"
        >
          <AppIcon
            :name="isPdf(file) ? 'pdf' : 'template'"
            :size="18"
            class="shrink-0 text-slate-400"
          />
          <span class="min-w-0 truncate font-medium">{{ file.original_name }}</span>
          <span
            v-if="isPdf(file)"
            class="shrink-0 text-[10px] text-brand"
          >Xem trước</span>
        </button>
        <span class="shrink-0 text-[10px] text-slate-400">{{ formatSize(file.size) }}</span>
        <a
          v-if="file.url"
          :href="file.url"
          class="shrink-0 text-slate-400 hover:text-brand"
          :title="`Tải ${file.original_name}`"
          @click.stop
        >
          <AppIcon
            name="download"
            :size="16"
          />
        </a>
        <button
          v-if="canUpload"
          type="button"
          class="shrink-0 text-slate-400 hover:text-rose-500"
          @click="removeAttachment(file)"
        >
          <AppIcon
            name="delete"
            :size="16"
          />
        </button>
      </li>
    </ul>

    <ul
      v-if="missingFiles.length"
      class="space-y-1 text-xs text-slate-400"
    >
      <li
        v-for="file in missingFiles"
        :key="file.id"
      >
        {{ file.original_name }} (file không còn trên máy chủ)
      </li>
    </ul>

    <p
      v-if="!attachments.length && !pending.length && !canUpload"
      class="rounded-xl border border-dashed border-slate-200 p-3 text-xs text-slate-400 dark:border-slate-600"
    >
      Chưa có file đính kèm.
    </p>
    <p
      v-else-if="!inline && !attachments.length && !pending.length && !pendingFiles.length && canUpload"
      class="text-center text-xs text-slate-400"
    >
      Chưa có ảnh — khu vực phía trên để thêm.
    </p>

    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-100 ease-in"
        leave-to-class="opacity-0"
      >
        <div
          v-if="lightboxOpen && lightboxItem"
          class="fixed inset-0 z-[70] flex flex-col bg-black/92 p-4"
          role="dialog"
          aria-modal="true"
          :aria-label="lightboxItem.original_name"
        >
          <div class="flex shrink-0 items-center justify-between gap-3 text-white">
            <p class="min-w-0 truncate text-sm font-medium">
              {{ lightboxItem.original_name }}
            </p>
            <div class="flex items-center gap-2">
              <a
                :href="lightboxItem.url"
                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs hover:bg-white/10"
              >
                <AppIcon
                  name="download"
                  :size="14"
                />
                Tải về
              </a>
              <button
                type="button"
                class="grid h-9 w-9 place-items-center rounded-lg hover:bg-white/10"
                aria-label="Đóng"
                @click="closeLightbox"
              >
                <AppIcon
                  name="close"
                  :size="20"
                />
              </button>
            </div>
          </div>
          <div class="relative flex min-h-0 flex-1 items-center justify-center py-4">
            <button
              v-if="imageFiles.length > 1"
              type="button"
              class="absolute left-0 z-10 grid h-10 w-10 place-items-center rounded-full bg-black/40 text-white hover:bg-black/60 disabled:opacity-30"
              :disabled="lightboxIndex <= 0"
              aria-label="Ảnh trước"
              @click="lightboxPrev"
            >
              <AppIcon
                name="chevron-left"
                :size="22"
              />
            </button>
            <img
              :src="lightboxItem.url"
              :alt="lightboxItem.original_name"
              class="max-h-[min(78vh,900px)] max-w-full object-contain"
            >
            <button
              v-if="imageFiles.length > 1"
              type="button"
              class="absolute right-0 z-10 grid h-10 w-10 place-items-center rounded-full bg-black/40 text-white hover:bg-black/60 disabled:opacity-30"
              :disabled="lightboxIndex >= imageFiles.length - 1"
              aria-label="Ảnh sau"
              @click="lightboxNext"
            >
              <AppIcon
                name="chevron-right"
                :size="22"
              />
            </button>
          </div>
          <p
            v-if="imageFiles.length > 1"
            class="shrink-0 text-center text-xs text-white/70"
          >
            {{ lightboxIndex + 1 }} / {{ imageFiles.length }}
          </p>
        </div>
      </Transition>
    </Teleport>

    <Modal
      :show="!!pdfPreview"
      :title="pdfPreview?.original_name || 'Xem trước PDF'"
      max-width="max-w-5xl"
      @close="pdfPreview = null"
    >
      <iframe
        v-if="pdfPreview?.url"
        :src="pdfPreview.url"
        class="h-[min(70vh,640px)] w-full rounded-lg border border-slate-200 dark:border-slate-600"
        title="Xem trước PDF"
      />
    </Modal>
  </div>
</template>
