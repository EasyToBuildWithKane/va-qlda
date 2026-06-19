<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    articleSlug: { type: String, required: true },
    images: { type: Array, default: () => [] },
});

const dialog = useDialog();
const altEdits = ref({});
const uploading = ref(false);
const fileInput = ref(null);

watch(
    () => props.images,
    (imgs) => {
        imgs.forEach((img) => {
            altEdits.value[img.id] = img.alt_text ?? '';
        });
    },
    { immediate: true },
);

function openPicker() {
    fileInput.value?.click();
}

async function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    uploading.value = true;
    const form = new FormData();
    form.append('image', file);
    router.post(route('knowledge-base.articles.gallery.store', props.articleSlug), form, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            uploading.value = false;
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

function saveAlt(img) {
    const alt = altEdits.value[img.id] ?? img.alt_text ?? '';
    router.patch(route('knowledge-base.gallery.update', img.id), { alt_text: alt }, { preserveScroll: true });
}

async function removeImage(img) {
    if (!await dialog.confirm({
        title: 'Xóa ảnh',
        message: 'Xóa ảnh này khỏi thư viện?',
        tone: 'danger',
        confirmText: 'Xóa',
    })) return;
    router.delete(route('knowledge-base.gallery.destroy', img.id), { preserveScroll: true });
}

</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between gap-2">
      <p class="text-sm font-medium text-slate-700">
        Thư viện ảnh
      </p>
      <button
        type="button"
        class="btn-ghost inline-flex h-8 items-center gap-1 text-xs"
        :disabled="uploading"
        @click="openPicker"
      >
        <AppIcon
          name="upload"
          :size="14"
        />
        {{ uploading ? 'Đang tải…' : 'Thêm ảnh' }}
      </button>
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="hidden"
        @change="onFileChange"
      >
    </div>
    <p class="text-xs text-slate-400">
      Ảnh hiển thị trên trang bài viết (khác ảnh chèn trong nội dung). Nhập mô tả alt để hỗ trợ truy cập.
    </p>
    <div
      v-if="!images.length"
      class="rounded-btn border border-dashed border-slate-200 p-6 text-center text-xs text-slate-400"
    >
      Chưa có ảnh trong thư viện.
    </div>
    <ul
      v-else
      class="grid gap-3 sm:grid-cols-2"
    >
      <li
        v-for="img in images"
        :key="img.id"
        class="rounded-btn border border-slate-100 p-2"
      >
        <img
          :src="img.url"
          :alt="img.alt_text || img.original_name"
          class="mb-2 h-32 w-full rounded object-cover"
        >
        <input
          v-model="altEdits[img.id]"
          class="input mb-2 w-full text-xs"
          placeholder="Mô tả alt"
        >
        <div class="flex gap-2">
          <button
            type="button"
            class="btn-ghost flex-1 text-xs"
            @click="saveAlt(img)"
          >
            Lưu alt
          </button>
          <button
            type="button"
            class="btn-ghost text-xs text-rose-600"
            @click="removeImage(img)"
          >
            Xóa
          </button>
        </div>
      </li>
    </ul>
  </div>
</template>
