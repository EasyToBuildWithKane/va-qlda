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
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits([
    'select-folder',
    'select-file',
    'toggle-row',
    'toggle-all',
    'rename-item',
    'preview-file',
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
    if (item.can_edit_content || item.preview_kind === 'text') return 'documents';
    return 'documents';
};

const typeIconClass = (item) => {
    if (item.is_folder) return 'text-amber-500';
    if (item.is_image) return 'text-violet-500';
    if (item.is_pdf || (item.original_name || '').toLowerCase().endsWith('.pdf')) return 'text-rose-500';
    if (item.is_external_link) return 'text-teal-600';
    return 'text-sky-500';
};

const formatLabel = (item, fileExt) => {
    if (item.is_category) return 'danh mục';
    if (item.is_folder) return 'thư mục';
    return (fileExt(item.original_name) || 'file').toLowerCase();
};

const showActions = (canEdit, canDelete) => canEdit || canDelete;
</script>

<template>
  <div class="w-full min-w-0 overflow-hidden rounded-lg border border-slate-200/80 dark:border-slate-700">
    <table class="w-full table-fixed border-collapse text-left text-[13px]">
      <colgroup>
        <col class="w-9">
        <col>
        <col class="w-[5.25rem]">
        <col
          v-if="!compact"
          class="w-[4.75rem]"
        >
        <col
          v-if="!compact"
          class="w-[8.25rem]"
        >
        <col
          v-if="!compact"
          class="w-10"
        >
        <col
          v-if="showActions(canEdit, canDelete)"
          class="w-[7.5rem]"
        >
      </colgroup>
      <thead>
        <tr class="border-b border-slate-200/80 bg-slate-50/90 text-[11px] font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-950/50 dark:text-slate-400">
          <th class="px-2 py-2">
            <input
              type="checkbox"
              class="h-3.5 w-3.5 rounded border-slate-300 text-brand focus:ring-brand/30"
              :checked="allChecked(folders, files, selectedIds)"
              aria-label="Chọn tất cả"
              @change="emit('toggle-all', $event.target.checked)"
            >
          </th>
          <th class="px-1.5 py-2 font-semibold">
            Tên
          </th>
          <th class="px-1.5 py-2 font-semibold">
            Dung lượng
          </th>
          <th
            v-if="!compact"
            class="px-1.5 py-2 font-semibold"
          >
            Định dạng
          </th>
          <th
            v-if="!compact"
            class="px-1.5 py-2 font-semibold"
          >
            Ngày tạo
          </th>
          <th
            v-if="!compact"
            class="px-1 py-2 text-center font-semibold"
          >
            <span class="sr-only">Người tạo</span>
          </th>
          <th
            v-if="showActions(canEdit, canDelete)"
            class="px-1 py-2 text-right font-semibold"
          >
            Thao tác
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <tr
          v-for="folder in folders"
          :key="`folder-${folder.id}`"
          class="group cursor-pointer bg-white transition hover:bg-amber-50/50 dark:bg-slate-900 dark:hover:bg-amber-950/15"
          @click="emit('select-folder', folder)"
        >
          <td
            class="px-2 py-2 align-top"
            @click.stop
          >
            <input
              type="checkbox"
              class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-brand focus:ring-brand/30"
              :checked="isChecked(folder.id, selectedIds)"
              :aria-label="`Chọn ${folder.original_name}`"
              @change="emit('toggle-row', folder.id)"
            >
          </td>
          <td class="min-w-0 px-1.5 py-2 align-top">
            <div class="flex min-w-0 items-start gap-2">
              <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-md bg-amber-50 ring-1 ring-amber-200/70 dark:bg-amber-950/40 dark:ring-amber-800/50">
                <AppIcon
                  :name="folder.icon || 'folder'"
                  :size="15"
                  class="text-amber-600 dark:text-amber-400"
                />
              </span>
              <span
                class="min-w-0 flex-1 break-words font-medium leading-snug text-slate-800 dark:text-slate-100"
                :title="folder.original_name"
              >
                {{ folder.original_name }}
              </span>
            </div>
          </td>
          <td class="whitespace-nowrap px-1.5 py-2 align-top tabular-nums text-slate-500">
            {{ formatSize(folder._size ?? 0) }}
          </td>
          <td
            v-if="!compact"
            class="px-1.5 py-2 align-top text-slate-500"
          >
            {{ formatLabel(folder, fileExt) }}
          </td>
          <td
            v-if="!compact"
            class="whitespace-nowrap px-1.5 py-2 align-top tabular-nums text-slate-500"
          >
            {{ folder.created_at ? formatDate(folder.created_at) : displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}
          </td>
          <td
            v-if="!compact"
            class="px-1 py-2 align-top"
          >
            <div class="flex justify-center">
              <Avatar
                v-if="folder.uploaded_by"
                :name="folder.uploaded_by.name"
                :src="folder.uploaded_by.avatar_path"
                :size="24"
              />
              <span
                v-else
                class="inline-block h-6 w-6 rounded-full bg-slate-100 ring-1 ring-slate-200/80 dark:bg-slate-800 dark:ring-slate-700"
                :title="EMPTY_LABELS.notUpdated"
                :aria-label="EMPTY_LABELS.notUpdated"
              />
            </div>
          </td>
          <td
            v-if="showActions(canEdit, canDelete)"
            class="px-1 py-2 align-top"
            @click.stop
          >
            <div class="flex items-center justify-end gap-0.5 opacity-70 transition group-hover:opacity-100">
              <button
                v-if="canEdit && !folder.is_category"
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                title="Đổi tên"
                @click="emit('rename-item', folder)"
              >
                <AppIcon
                  name="edit"
                  :size="13"
                />
              </button>
              <button
                v-if="canDelete && !folder.is_category"
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-300 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                title="Xoá"
                @click="emit('delete-item', folder)"
              >
                <AppIcon
                  name="delete"
                  :size="13"
                />
              </button>
            </div>
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
            class="px-2 py-2 align-top"
            @click.stop
          >
            <input
              type="checkbox"
              class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-brand focus:ring-brand/30"
              :checked="isChecked(file.id, selectedIds)"
              :aria-label="`Chọn ${file.original_name}`"
              @change="emit('toggle-row', file.id)"
            >
          </td>
          <td class="min-w-0 px-1.5 py-2 align-top">
            <div class="flex min-w-0 items-start gap-2">
              <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-md bg-slate-50 ring-1 ring-slate-200/70 dark:bg-slate-800 dark:ring-slate-700">
                <AppIcon
                  :name="typeIcon(file)"
                  :size="14"
                  :class="typeIconClass(file)"
                />
              </span>
              <span class="min-w-0 flex-1 break-words font-medium leading-snug text-slate-800 dark:text-slate-100">
                {{ file.original_name }}
              </span>
            </div>
          </td>
          <td class="whitespace-nowrap px-1.5 py-2 align-top tabular-nums text-slate-500">
            {{ file.is_external_link ? 'Link' : formatSize(file.size, file) }}
          </td>
          <td
            v-if="!compact"
            class="px-1.5 py-2 align-top lowercase text-slate-500"
          >
            {{ formatLabel(file, fileExt) }}
          </td>
          <td
            v-if="!compact"
            class="whitespace-nowrap px-1.5 py-2 align-top tabular-nums text-slate-500"
          >
            {{ file.created_at ? formatDate(file.created_at) : displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}
          </td>
          <td
            v-if="!compact"
            class="px-1 py-2 align-top"
          >
            <div class="flex justify-center">
              <Avatar
                v-if="file.uploaded_by"
                :name="file.uploaded_by.name"
                :src="file.uploaded_by.avatar_path"
                :size="24"
              />
              <span
                v-else
                class="inline-block h-6 w-6 rounded-full bg-slate-100 ring-1 ring-slate-200/80 dark:bg-slate-800 dark:ring-slate-700"
                :title="EMPTY_LABELS.notUpdated"
                :aria-label="EMPTY_LABELS.notUpdated"
              />
            </div>
          </td>
          <td
            v-if="showActions(canEdit, canDelete)"
            class="px-1 py-2 align-top"
            @click.stop
          >
            <div class="flex items-center justify-end gap-0.5 opacity-70 transition group-hover:opacity-100">
              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-sky-50 hover:text-sky-700 dark:hover:bg-sky-950/40"
                title="Xem trước"
                @click="emit('preview-file', file)"
              >
                <AppIcon
                  name="eye"
                  :size="13"
                />
              </button>
              <button
                v-if="canEdit"
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                title="Đổi tên"
                @click="emit('rename-item', file)"
              >
                <AppIcon
                  name="edit"
                  :size="13"
                />
              </button>
              <button
                v-if="canDelete"
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-300 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                title="Xoá"
                @click="emit('delete-item', file)"
              >
                <AppIcon
                  name="delete"
                  :size="13"
                />
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
