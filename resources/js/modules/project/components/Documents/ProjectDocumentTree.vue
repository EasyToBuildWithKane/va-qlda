<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    nodes: { type: Array, default: () => [] },
    depth: { type: Number, default: 0 },
    selectedId: { type: [Number, String, null], default: null },
    activeFolderId: { type: [Number, String, null], default: null },
    expandedIds: { type: Object, required: true },
    listBadge: { type: Function, required: true },
    formatSize: { type: Function, required: true },
    canUpload: { type: Boolean, default: false },
});

const emit = defineEmits(['select-file', 'select-folder', 'toggle-folder', 'create-subfolder']);

const isExpanded = (id, expandedIds) => expandedIds[id] !== false;

const toggleExpand = (node, event) => {
    event.stopPropagation();
    emit('toggle-folder', node.id);
};

const onSelectFolder = (node) => {
    emit('select-folder', node);
};

const onSelectFile = (node) => {
    emit('select-file', node);
};

const depthAccentClass = (depth) => {
    const tones = [
        'doc-tree-row--depth-0',
        'doc-tree-row--depth-1',
        'doc-tree-row--depth-2',
        'doc-tree-row--depth-3',
        'doc-tree-row--depth-4',
    ];
    return tones[Math.min(depth, tones.length - 1)];
};

const folderMetaLabel = (node) => {
    const subs = node.subfolder_count ?? 0;
    const files = node.file_count ?? 0;
    const parts = [];
    if (subs > 0) parts.push(`${subs} thư mục con`);
    if (files > 0) parts.push(`${files} tài liệu`);
    return parts.length ? parts.join(' · ') : 'Trống — thêm thư mục con hoặc file';
};

const depthLabel = (depth) => (depth === 0 ? 'Gốc' : `Cấp ${depth + 1}`);
</script>

<template>
  <ul
    class="doc-tree-level list-none"
    :class="depth === 0 ? 'doc-tree-level--root px-1.5 py-1 sm:px-2' : 'doc-tree-level--nested'"
    role="group"
  >
    <li
      v-for="(node, index) in nodes"
      :key="node.id"
      class="doc-tree-item"
      :class="{ 'doc-tree-item--last': index === nodes.length - 1 }"
    >
      <div
        v-if="depth > 0"
        class="doc-tree-guide"
        aria-hidden="true"
      >
        <span class="doc-tree-guide__elbow" />
        <span class="doc-tree-guide__stem" />
      </div>

      <div
        class="doc-tree-row group/row relative flex min-w-0 items-stretch gap-0.5 rounded-lg transition"
        :class="[
          depthAccentClass(depth),
          node.is_folder && activeFolderId === node.id ? 'doc-tree-row--active-folder' : '',
          !node.is_folder && selectedId === node.id ? 'doc-tree-row--active-file' : '',
        ]"
      >
        <button
          v-if="node.is_folder"
          type="button"
          class="doc-tree-toggle mt-1.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-500 transition hover:bg-white/80 hover:text-brand dark:hover:bg-slate-800"
          :aria-label="isExpanded(node.id, expandedIds) ? 'Thu gọn' : 'Mở rộng'"
          @click="toggleExpand(node, $event)"
        >
          <AppIcon
            :name="isExpanded(node.id, expandedIds) ? 'chevron-down' : 'chevron-right'"
            :size="15"
          />
        </button>
        <span
          v-else
          class="mt-1.5 w-8 shrink-0"
          aria-hidden="true"
        />

        <button
          type="button"
          class="flex min-w-0 flex-1 items-start gap-2 py-1.5 pr-1 text-left sm:gap-2.5 sm:py-2 sm:pr-2"
          @click="node.is_folder ? onSelectFolder(node) : onSelectFile(node)"
        >
          <span
            class="relative grid h-9 w-9 shrink-0 place-items-center rounded-lg shadow-sm ring-1 sm:h-10 sm:w-10"
            :class="node.is_folder
              ? (isExpanded(node.id, expandedIds)
                ? 'bg-gradient-to-br from-amber-100 to-amber-50 text-amber-800 ring-amber-200/90 dark:from-amber-900/60 dark:to-amber-950/40 dark:text-amber-100 dark:ring-amber-700/50'
                : 'bg-gradient-to-br from-slate-100 to-white text-slate-600 ring-slate-200/90 dark:from-slate-800 dark:to-slate-900 dark:text-slate-300 dark:ring-slate-600')
              : node.is_image
                ? 'overflow-hidden ring-rose-200/80'
                : (node.is_google_doc || node.is_google_sheet)
                  ? 'bg-emerald-50 text-emerald-700 ring-emerald-200/80'
                  : 'bg-white text-slate-500 ring-slate-200/90 dark:bg-slate-800 dark:ring-slate-600'"
          >
            <AppIcon
              v-if="node.is_folder"
              :name="isExpanded(node.id, expandedIds) ? 'folder-open' : 'folder'"
              :size="18"
            />
            <img
              v-else-if="node.is_image && node.url"
              :src="node.url"
              :alt="node.original_name"
              class="h-full w-full object-cover"
            >
            <span
              v-else
              class="text-[9px] font-bold uppercase"
            >{{ listBadge(node) }}</span>
          </span>

          <span class="min-w-0 flex-1 pt-0.5">
            <span class="flex flex-wrap items-center gap-1.5">
              <span
                class="line-clamp-2 text-xs font-semibold leading-snug sm:text-sm"
                :class="node.is_folder ? 'text-slate-800 dark:text-slate-100' : 'text-slate-800 dark:text-slate-100'"
              >
                {{ node.original_name }}
              </span>
              <span
                v-if="node.is_folder"
                class="hidden shrink-0 rounded-md bg-white/70 px-1.5 py-px text-[10px] font-medium uppercase tracking-wide text-slate-500 ring-1 ring-slate-200/80 sm:inline dark:bg-slate-900/60 dark:ring-slate-700"
              >
                {{ depthLabel(depth) }}
              </span>
            </span>
            <span class="mt-0.5 block text-[11px] leading-snug text-slate-500 sm:text-xs">
              <template v-if="node.is_folder">
                {{ folderMetaLabel(node) }}
              </template>
              <template v-else>
                {{ formatSize(node.size, node) }}
                <span v-if="node.uploaded_by?.name"> · {{ node.uploaded_by.name.split(' ').pop() }}</span>
              </template>
            </span>
          </span>
        </button>

        <button
          v-if="node.is_folder && canUpload"
          type="button"
          class="doc-tree-add-sub mt-1.5 mr-1 grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-dashed border-slate-200/90 text-slate-400 opacity-0 transition hover:border-brand/40 hover:bg-brand/5 hover:text-brand focus-visible:opacity-100 group-hover/row:opacity-100 dark:border-slate-600 dark:hover:border-brand/50 sm:mr-1.5"
          title="Tạo thư mục con"
          @click.stop="emit('create-subfolder', node.id)"
        >
          <AppIcon
            name="plus"
            :size="14"
          />
        </button>
      </div>

      <div
        v-if="node.is_folder && isExpanded(node.id, expandedIds)"
        class="doc-tree-children"
      >
        <ProjectDocumentTree
          v-if="node.children?.length"
          :nodes="node.children"
          :depth="depth + 1"
          :selected-id="selectedId"
          :active-folder-id="activeFolderId"
          :expanded-ids="expandedIds"
          :list-badge="listBadge"
          :format-size="formatSize"
          :can-upload="canUpload"
          @select-file="emit('select-file', $event)"
          @select-folder="emit('select-folder', $event)"
          @toggle-folder="emit('toggle-folder', $event)"
          @create-subfolder="emit('create-subfolder', $event)"
        />
        <div
          v-else-if="canUpload"
          class="doc-tree-empty-nested ml-10 mr-2 mb-2 mt-1 rounded-lg border border-dashed border-slate-200/90 bg-slate-50/80 px-3 py-2.5 text-center dark:border-slate-700 dark:bg-slate-800/40"
        >
          <p class="text-[11px] text-slate-500 sm:text-xs">
            Thư mục trống
          </p>
          <button
            type="button"
            class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-brand hover:underline"
            @click="emit('create-subfolder', node.id)"
          >
            <AppIcon
              name="plus"
              :size="12"
            />
            Tạo thư mục con
          </button>
        </div>
      </div>
    </li>
  </ul>
</template>

<script>
export default {
    name: 'ProjectDocumentTree',
};
</script>

<style scoped>
.doc-tree-level--root {
    background: linear-gradient(180deg, rgb(248 250 252 / 0.5) 0%, transparent 48%);
}

.doc-tree-item {
    position: relative;
}

.doc-tree-guide {
    pointer-events: none;
    position: absolute;
    left: 0.35rem;
    top: 0;
    bottom: 0;
    width: 1.25rem;
}

.doc-tree-guide__stem {
    position: absolute;
    left: 0.55rem;
    top: 0;
    bottom: 0;
    width: 1px;
    background: linear-gradient(180deg, rgb(203 213 225 / 0.9), rgb(203 213 225 / 0.35));
}

.doc-tree-item--last .doc-tree-guide__stem {
    bottom: 50%;
}

.doc-tree-guide__elbow {
    position: absolute;
    left: 0.55rem;
    top: 1.35rem;
    height: 1px;
    width: 0.65rem;
    background: rgb(203 213 225 / 0.95);
}

.doc-tree-children {
    position: relative;
    margin-left: 0.35rem;
    padding-left: 0.85rem;
    border-left: 2px solid rgb(226 232 240 / 0.85);
}

:global(.dark) .doc-tree-children {
    border-left-color: rgb(51 65 85 / 0.85);
}

.doc-tree-row--depth-0 {
    background: transparent;
}

.doc-tree-row--depth-1 {
    background: rgb(255 255 255 / 0.55);
}

.doc-tree-row--depth-2 {
    background: rgb(248 250 252 / 0.85);
}

.doc-tree-row--depth-3,
.doc-tree-row--depth-4 {
    background: rgb(241 245 249 / 0.65);
}

:global(.dark) .doc-tree-row--depth-1 {
    background: rgb(15 23 42 / 0.35);
}

:global(.dark) .doc-tree-row--depth-2,
:global(.dark) .doc-tree-row--depth-3,
:global(.dark) .doc-tree-row--depth-4 {
    background: rgb(15 23 42 / 0.5);
}

.doc-tree-row--active-folder {
    background: rgb(154 0 54 / 0.08);
    box-shadow: inset 3px 0 0 0 rgb(154 0 54 / 0.75);
}

.doc-tree-row--active-file {
    background: rgb(154 0 54 / 0.06);
    box-shadow: inset 3px 0 0 0 rgb(154 0 54 / 0.45);
}

.doc-tree-row:hover {
    background: rgb(248 250 252 / 0.95);
}

:global(.dark) .doc-tree-row:hover {
    background: rgb(30 41 59 / 0.55);
}

.doc-tree-row--active-folder:hover,
.doc-tree-row--active-file:hover {
    background: rgb(154 0 54 / 0.1);
}
</style>
