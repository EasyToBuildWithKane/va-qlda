<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
    selectedIds: { type: Array, default: () => [] },
    formatSize: { type: Function, required: true },
    fileExt: { type: Function, required: true },
    compact: { type: Boolean, default: false },
    canDrag: { type: Boolean, default: false },
    dropTargetId: { type: [Number, String, null], default: null },
});

const emit = defineEmits([
    'select-folder',
    'select-file',
    'toggle-row',
    'toggle-all',
    'contextmenu-item',
    'drag-start-item',
    'drag-end-item',
    'drop-on-folder',
    'drag-over-folder',
    'drag-leave-folder',
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
    if (
        item.can_edit_content
        || ['text', 'markdown', 'html'].includes(item.preview_kind)
    ) return 'documents';
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

const colCount = (compact) => (compact ? 3 : 4);

const isEmpty = (folders, files) => !folders.length && !files.length;
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
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <tr v-if="isEmpty(folders, files)">
          <td
            :colspan="colCount(compact)"
            class="px-3 py-6 text-center text-sm text-slate-500 dark:text-slate-400"
          >
            Chưa có tài liệu trong thư mục này
          </td>
        </tr>
        <tr
          v-for="folder in folders"
          :key="`folder-${folder.id}`"
          class="group cursor-pointer bg-white transition hover:bg-violet-50/50 dark:bg-slate-900 dark:hover:bg-violet-950/15"
          :class="dropTargetId === folder.id && !folder.is_category ? 'bg-brand/[0.08] ring-2 ring-inset ring-brand/30' : ''"
          :draggable="canDrag && !folder.is_category"
          @click="emit('select-folder', folder)"
          @contextmenu.prevent="!folder.is_category && emit('contextmenu-item', { item: folder, event: $event })"
          @dragstart="canDrag && !folder.is_category && emit('drag-start-item', { item: folder, event: $event })"
          @dragend="emit('drag-end-item')"
          @dragover.prevent="!folder.is_category && emit('drag-over-folder', { folderId: folder.id, event: $event })"
          @dragleave="!folder.is_category && emit('drag-leave-folder', { folderId: folder.id, event: $event })"
          @drop.prevent="!folder.is_category && emit('drop-on-folder', { folderId: folder.id, event: $event })"
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
              <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-md bg-violet-50 ring-1 ring-violet-200/70 dark:bg-violet-950/40 dark:ring-violet-800/50">
                <AppIcon
                  :name="folder.icon || 'folder'"
                  :size="15"
                  class="text-violet-600 dark:text-violet-400"
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
        </tr>

        <tr
          v-for="file in files"
          :key="`file-${file.id}`"
          class="group cursor-pointer bg-white transition hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/60"
          :class="selectedId === file.id ? 'bg-brand/[0.04] dark:bg-brand/10' : ''"
          :draggable="canDrag"
          @click="emit('select-file', file)"
          @contextmenu.prevent="emit('contextmenu-item', { item: file, event: $event })"
          @dragstart="canDrag && emit('drag-start-item', { item: file, event: $event })"
          @dragend="emit('drag-end-item')"
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
        </tr>
      </tbody>
    </table>
  </div>
</template>
