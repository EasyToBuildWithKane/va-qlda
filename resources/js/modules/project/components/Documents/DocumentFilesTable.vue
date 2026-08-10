<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    selectedIds: { type: Array, default: () => [] },
    formatSize: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    fileExt: { type: Function, required: true },
    canDelete: { type: Boolean, default: false },
});

const emit = defineEmits([
    'select-folder',
    'select-file',
    'toggle-row',
    'toggle-all',
    'delete-item',
]);

const isChecked = (id, selectedIds) => selectedIds.map(String).includes(String(id));

const allIds = (folders, files) => [
    ...folders.map((f) => f.id),
    ...files.map((f) => f.id),
];

const allChecked = (folders, files, selectedIds) => {
    const ids = allIds(folders, files);
    return ids.length > 0 && ids.every((id) => isChecked(id, selectedIds));
};

const typeIcon = (item) => {
    if (item.is_folder) return 'folder';
    if (item.is_image) return 'image';
    if (item.is_pdf || (item.original_name || '').toLowerCase().endsWith('.pdf')) return 'pdf';
    if (item.is_external_link) return 'link';
    return 'documents';
};

const typeIconClass = (item) => {
    if (item.is_folder) return 'text-sky-500';
    if (item.is_image) return 'text-violet-500';
    if (item.is_pdf || (item.original_name || '').toLowerCase().endsWith('.pdf')) return 'text-rose-500';
    if (item.is_external_link) return 'text-teal-600';
    return 'text-sky-500';
};
</script>

<template>
  <div class="overflow-hidden rounded-xl border border-slate-200/80 dark:border-slate-700">
    <div class="overflow-x-auto">
      <table class="min-w-full border-collapse text-left text-sm">
        <thead>
          <tr class="border-b border-slate-200/80 bg-slate-50/80 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-700 dark:bg-slate-950/50 dark:text-slate-400">
            <th class="w-10 px-3 py-2.5">
              <input
                type="checkbox"
                class="h-3.5 w-3.5 rounded border-slate-300 text-brand focus:ring-brand/30"
                :checked="allChecked(folders, files, selectedIds)"
                aria-label="Chọn tất cả"
                @change="emit('toggle-all', $event.target.checked)"
              >
            </th>
            <th class="min-w-[14rem] px-2 py-2.5">
              Tên
            </th>
            <th class="w-28 px-2 py-2.5">
              Dung lượng
            </th>
            <th class="w-24 px-2 py-2.5">
              Định dạng
            </th>
            <th class="w-40 px-2 py-2.5">
              Ngày tạo
            </th>
            <th class="w-20 px-2 py-2.5 text-center">
              Người tạo
            </th>
            <th
              v-if="canDelete"
              class="w-12 px-2 py-2.5"
            />
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <tr
            v-for="folder in folders"
            :key="`folder-${folder.id}`"
            class="group cursor-pointer bg-white transition hover:bg-sky-50/50 dark:bg-slate-900 dark:hover:bg-sky-950/20"
            @click="emit('select-folder', folder)"
          >
            <td
              class="px-3 py-2.5"
              @click.stop
            >
              <input
                type="checkbox"
                class="h-3.5 w-3.5 rounded border-slate-300 text-brand focus:ring-brand/30"
                :checked="isChecked(folder.id, selectedIds)"
                :aria-label="`Chọn ${folder.original_name}`"
                @change="emit('toggle-row', folder.id)"
              >
            </td>
            <td class="px-2 py-2.5">
              <div class="flex min-w-0 items-center gap-2.5">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#EEF1FF] dark:bg-sky-950/40">
                  <AppIcon
                    :name="folder.icon || 'folder'"
                    :size="16"
                    class="text-sky-500"
                  />
                </span>
                <span class="min-w-0">
                  <span class="block truncate font-medium text-slate-800 dark:text-slate-100">
                    {{ folder.original_name }}
                  </span>
                  <span
                    v-if="folder.is_category && folder.description"
                    class="mt-0.5 block truncate text-[11px] text-slate-400"
                  >
                    {{ folder.description }}
                  </span>
                </span>
              </div>
            </td>
            <td class="px-2 py-2.5 tabular-nums text-slate-500">
              {{ formatSize(folder._size ?? 0) }}
            </td>
            <td class="px-2 py-2.5 text-slate-500">
              {{ folder.is_category ? 'danh mục' : 'thư mục' }}
            </td>
            <td class="px-2 py-2.5 tabular-nums text-slate-500">
              {{ formatDate(folder.created_at) }}
            </td>
            <td class="px-2 py-2.5">
              <div class="flex justify-center">
                <Avatar
                  v-if="folder.uploaded_by"
                  :name="folder.uploaded_by.name"
                  :src="folder.uploaded_by.avatar_path"
                  :size="28"
                />
                <span
                  v-else
                  class="text-[11px] text-slate-400"
                >{{ displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}</span>
              </div>
            </td>
            <td
              v-if="canDelete"
              class="px-2 py-2.5"
              @click.stop
            >
              <button
                v-if="!folder.is_category"
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-300 opacity-0 transition hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100 dark:hover:bg-rose-950/40"
                title="Xoá"
                @click="emit('delete-item', folder)"
              >
                <AppIcon
                  name="delete"
                  :size="13"
                />
              </button>
            </td>
          </tr>

          <tr
            v-for="file in files"
            :key="`file-${file.id}`"
            class="group cursor-pointer bg-white transition hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/60"
            :class="selectedId === file.id ? 'bg-brand/[0.04] dark:bg-brand/10' : ''"
            @click="emit('select-file', file)"
          >
            <td
              class="px-3 py-2.5"
              @click.stop
            >
              <input
                type="checkbox"
                class="h-3.5 w-3.5 rounded border-slate-300 text-brand focus:ring-brand/30"
                :checked="isChecked(file.id, selectedIds)"
                :aria-label="`Chọn ${file.original_name}`"
                @change="emit('toggle-row', file.id)"
              >
            </td>
            <td class="px-2 py-2.5">
              <div class="flex min-w-0 items-center gap-2.5">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-slate-50 ring-1 ring-slate-200/70 dark:bg-slate-800 dark:ring-slate-700">
                  <AppIcon
                    :name="typeIcon(file)"
                    :size="15"
                    :class="typeIconClass(file)"
                  />
                </span>
                <span class="truncate font-medium text-slate-800 dark:text-slate-100">
                  {{ file.original_name }}
                </span>
              </div>
            </td>
            <td class="px-2 py-2.5 tabular-nums text-slate-500">
              {{ file.is_external_link ? 'Link' : formatSize(file.size, file) }}
            </td>
            <td class="px-2 py-2.5 lowercase text-slate-500">
              {{ file.is_folder ? 'thư mục' : (fileExt(file.original_name) || 'file').toLowerCase() }}
            </td>
            <td class="px-2 py-2.5 tabular-nums text-slate-500">
              {{ formatDate(file.created_at) }}
            </td>
            <td class="px-2 py-2.5">
              <div class="flex justify-center">
                <Avatar
                  v-if="file.uploaded_by"
                  :name="file.uploaded_by.name"
                  :src="file.uploaded_by.avatar_path"
                  :size="28"
                />
                <span
                  v-else
                  class="text-[11px] text-slate-400"
                >{{ displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}</span>
              </div>
            </td>
            <td
              v-if="canDelete"
              class="px-2 py-2.5"
              @click.stop
            >
              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-300 opacity-0 transition hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100 dark:hover:bg-rose-950/40"
                title="Xoá"
                @click="emit('delete-item', file)"
              >
                <AppIcon
                  name="delete"
                  :size="13"
                />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
