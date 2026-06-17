<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    contractId: { type: Number, required: true },
    attachments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] }, // [{value,label,color}]
    canManage: { type: Boolean, default: false },
});

const dialog = useDialog();
const toast = useToast();

const showUploadPanel = ref(false);
const mode = ref('file'); // file | link
const form = useForm({
    category: 'contract',
    title: '',
    notes: '',
    external_url: '',
    files: [],
});

function onFiles(e) {
    form.files = Array.from(e.target.files || []);
}

function submit() {
    if (mode.value === 'file' && !form.files.length) {
        toast.error('Hãy chọn ít nhất một file.');
        return;
    }
    if (mode.value === 'link' && !form.external_url) {
        toast.error('Hãy nhập link hồ sơ.');
        return;
    }
    form.post(`/contracts/${props.contractId}/attachments`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess() {
            form.reset('title', 'notes', 'external_url', 'files');
            showUploadPanel.value = false;
            router.reload({ only: ['contract'] });
        },
    });
}

function cancelUpload() {
    showUploadPanel.value = false;
    form.reset();
}

async function remove(a) {
    const ok = await dialog.confirm({
        title: 'Xoá hồ sơ?',
        message: `"${a.original_name}" sẽ bị xoá.`,
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    router.delete(`/contracts/${props.contractId}/attachments/${a.id}`, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['contract'] }),
    });
}

// Nhóm theo loại; sắp theo category label.
const grouped = computed(() => {
    const map = new Map();
    for (const a of props.attachments) {
        const key = a.category?.value ?? 'contract';
        if (!map.has(key)) map.set(key, { category: a.category, items: [] });
        map.get(key).items.push(a);
    }
    return [...map.values()];
});

function fileExtBadge(name = '', isExternal = false) {
    if (isExternal) return 'Link';
    const ext = String(name).split('.').pop()?.toLowerCase();
    const MAP = { pdf: 'PDF', doc: 'Word', docx: 'Word', xls: 'Excel', xlsx: 'Excel', ppt: 'PPT', pptx: 'PPT', png: 'Ảnh', jpg: 'Ảnh', jpeg: 'Ảnh' };
    return MAP[ext] || (ext ? ext.toUpperCase() : 'Tệp');
}

function fileExtColor(name = '', isExternal = false) {
    if (isExternal) return 'sky';
    const ext = String(name).split('.').pop()?.toLowerCase();
    if (ext === 'pdf') return 'rose';
    if (['doc', 'docx'].includes(ext)) return 'sky';
    if (['xls', 'xlsx'].includes(ext)) return 'emerald';
    if (['png', 'jpg', 'jpeg', 'webp'].includes(ext)) return 'violet';
    return 'slate';
}
</script>

<template>
  <div class="space-y-5">
    <!-- Toolbar -->
    <div class="flex items-center justify-between">
      <h3 class="font-display text-sm font-semibold text-slate-800">
        Hồ sơ hợp đồng
        <span
          v-if="attachments.length"
          class="ml-1 text-xs font-normal text-slate-400"
        >({{ attachments.length }} tài liệu)</span>
      </h3>
      <button
        v-if="canManage && !showUploadPanel"
        type="button"
        class="btn-primary h-8 gap-1.5 px-3 text-xs"
        @click="showUploadPanel = true"
      >
        <AppIcon
          name="upload"
          :size="13"
        /> Thêm hồ sơ
      </button>
    </div>

    <!-- Upload Panel -->
    <div
      v-if="showUploadPanel"
      class="rounded-xl border border-slate-200 bg-slate-50/60 p-4"
    >
      <p class="mb-3 font-display text-sm font-semibold text-slate-800">
        Thêm hồ sơ mới
      </p>

      <!-- Mode toggle -->
      <div class="mb-4 flex w-fit items-center gap-1 rounded-lg bg-slate-100 p-1 text-sm">
        <button
          type="button"
          class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
          :class="mode === 'file' ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-700'"
          @click="mode = 'file'"
        >
          <AppIcon
            name="upload"
            :size="12"
            class="mr-1"
          />Tải file lên
        </button>
        <button
          type="button"
          class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
          :class="mode === 'link' ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-700'"
          @click="mode = 'link'"
        >
          <AppIcon
            name="link"
            :size="12"
            class="mr-1"
          />Link ngoài
        </button>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <!-- Category -->
        <div>
          <label class="label">Loại hồ sơ</label>
          <select
            v-model="form.category"
            class="input h-10 w-full text-sm"
          >
            <option
              v-for="cat in categories"
              :key="cat.value"
              :value="cat.value"
            >
              {{ cat.label }}
            </option>
          </select>
        </div>

        <!-- File input -->
        <div v-if="mode === 'file'">
          <label class="label">Chọn file (PDF, DOCX, XLSX, ảnh…)</label>
          <input
            type="file"
            multiple
            class="input h-10 w-full text-sm file:mr-3 file:rounded file:border-0 file:bg-brand/10 file:px-2 file:py-1 file:text-xs file:font-medium file:text-brand"
            @change="onFiles"
          >
        </div>

        <!-- Link input -->
        <div v-else>
          <label class="label">Link (Google Drive, SharePoint…)</label>
          <input
            v-model="form.external_url"
            type="url"
            class="input h-10 w-full text-sm"
            placeholder="https://drive.google.com/…"
          >
          <p
            v-if="form.errors.external_url"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.external_url }}
          </p>
        </div>

        <div class="sm:col-span-2">
          <label class="label">Ghi chú (tùy chọn)</label>
          <input
            v-model="form.notes"
            type="text"
            class="input h-10 w-full text-sm"
            placeholder="Mô tả ngắn về tài liệu này…"
          >
        </div>
      </div>

      <div class="mt-4 flex items-center justify-end gap-2">
        <button
          type="button"
          class="btn-ghost h-9 px-3 text-sm"
          @click="cancelUpload"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary h-9 gap-1.5 px-3 text-sm"
          :disabled="form.processing"
          @click="submit"
        >
          <AppIcon
            name="upload"
            :size="14"
          />
          {{ form.processing ? 'Đang lưu…' : 'Thêm hồ sơ' }}
        </button>
      </div>
    </div>

    <!-- Grouped sections -->
    <div
      v-if="grouped.length"
      class="space-y-5"
    >
      <section
        v-for="g in grouped"
        :key="g.category?.value ?? 'default'"
      >
        <!-- Group header -->
        <div class="mb-3 flex items-center gap-2">
          <Badge
            :label="g.category?.label ?? 'Hồ sơ'"
            :color="g.category?.color ?? 'slate'"
          />
          <span class="text-xs text-slate-400">{{ g.items.length }} tài liệu</span>
        </div>

        <!-- Card grid -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div
            v-for="a in g.items"
            :key="a.id"
            class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition-shadow hover:shadow-md"
          >
            <!-- File type icon -->
            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100">
              <AppIcon
                :name="a.is_external ? 'link' : 'documents'"
                :size="18"
                class="text-slate-500"
              />
            </div>

            <!-- Content -->
            <div class="min-w-0 flex-1">
              <div class="flex items-start justify-between gap-1">
                <p class="truncate text-sm font-medium text-slate-800 leading-tight">
                  {{ a.original_name }}
                </p>
                <Badge
                  class="ml-1 shrink-0"
                  :label="fileExtBadge(a.original_name, a.is_external)"
                  :color="fileExtColor(a.original_name, a.is_external)"
                />
              </div>
              <p
                v-if="a.notes"
                class="mt-0.5 truncate text-xs text-slate-400"
              >
                {{ a.notes }}
              </p>
              <div class="mt-1.5 flex items-center gap-2">
                <span
                  v-if="a.version > 1"
                  class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500"
                >v{{ a.version }}</span>
                <a
                  v-if="a.url"
                  :href="a.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-xs font-medium text-brand hover:underline"
                >
                  {{ a.is_external ? 'Mở link' : 'Tải xuống' }}
                </a>
              </div>
            </div>

            <!-- Delete -->
            <button
              v-if="canManage"
              type="button"
              class="invisible mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded text-slate-300 hover:bg-rose-50 hover:text-rose-500 group-hover:visible"
              title="Xoá hồ sơ"
              @click="remove(a)"
            >
              <AppIcon
                name="delete"
                :size="13"
              />
            </button>
          </div>
        </div>
      </section>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!showUploadPanel"
      class="rounded-xl border-2 border-dashed border-slate-200 py-12 text-center"
    >
      <AppIcon
        name="documents"
        :size="32"
        class="mx-auto mb-3 text-slate-300"
      />
      <p class="text-sm font-medium text-slate-500">
        Chưa có hồ sơ nào
      </p>
      <p class="mt-1 text-xs text-slate-400">
        Tải lên file hoặc dán link Google Drive, SharePoint.
      </p>
      <button
        v-if="canManage"
        type="button"
        class="btn-primary mt-4 h-8 gap-1.5 px-3 text-xs"
        @click="showUploadPanel = true"
      >
        <AppIcon
          name="upload"
          :size="13"
        /> Thêm hồ sơ
      </button>
    </div>
  </div>
</template>
