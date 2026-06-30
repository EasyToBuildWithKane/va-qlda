<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { datetime } from '@/composables/useFormat';

defineProps({
    selected: { type: Object, required: true },
    activities: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    notesForm: { type: Object, required: true },
    editingNotes: { type: Boolean, default: false },
    editingLink: { type: Boolean, default: false },
    linkForm: { type: Object, required: true },
    formatSize: { type: Function, required: true },
    formatFileType: { type: Function, required: true },
    activityIcon: { type: Function, required: true },
    activityTone: { type: Function, required: true },
});

const emit = defineEmits([
    'edit-notes',
    'cancel-notes',
    'save-notes',
    'edit-link',
    'cancel-link',
    'save-link',
    'replace',
    'update:notes',
    'update:link-title',
    'update:link-url',
]);
</script>

<template>
  <div class="flex flex-col gap-3 p-3">
    <section class="overflow-hidden rounded-lg border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <header class="border-b border-slate-100 bg-slate-50/90 px-3 py-1.5 dark:border-slate-800 dark:bg-slate-800/50">
        <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          {{ selected.is_external_link ? 'Thông tin link' : 'Thông tin file' }}
        </h4>
      </header>
      <ul class="divide-y divide-slate-100 text-xs dark:divide-slate-800">
        <li class="flex items-center justify-between gap-2 px-3 py-2">
          <span class="text-slate-500">Người tải</span>
          <span class="flex min-w-0 items-center gap-1.5 font-medium text-slate-800 dark:text-slate-100">
            <Avatar
              v-if="selected.uploaded_by"
              :name="selected.uploaded_by.name"
              :size="20"
            />
            <span class="truncate">{{ selected.uploaded_by?.name ?? '—' }}</span>
          </span>
        </li>
        <li class="flex items-center justify-between gap-2 px-3 py-2">
          <span class="text-slate-500">Ngày tải</span>
          <span class="text-slate-800 dark:text-slate-200">{{ datetime(selected.created_at) }}</span>
        </li>
        <li class="flex items-center justify-between gap-2 px-3 py-2">
          <span class="text-slate-500">Dung lượng</span>
          <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold tabular-nums dark:bg-slate-800">
            {{ formatSize(selected.size, selected) }}
          </span>
        </li>
        <li class="flex items-center justify-between gap-2 px-3 py-2">
          <span class="text-slate-500">Định dạng</span>
          <span
            class="max-w-[55%] truncate rounded bg-brand/10 px-1.5 py-0.5 font-semibold text-brand"
            :title="selected.mime_type"
          >{{ formatFileType(selected) }}</span>
        </li>
      </ul>
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <header class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/90 px-3 py-1.5 dark:border-slate-800 dark:bg-slate-800/50">
        <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          Ghi chú
        </h4>
        <button
          v-if="canEdit && !editingNotes"
          type="button"
          class="text-xs font-medium text-brand hover:underline"
          @click="emit('edit-notes')"
        >
          Sửa
        </button>
      </header>
      <div class="px-3 py-2">
        <textarea
          v-if="editingNotes && canEdit"
          :value="notesForm.notes"
          rows="2"
          class="input w-full resize-none text-xs"
          placeholder="Ghi chú nội bộ…"
          @input="emit('update:notes', $event.target.value)"
        />
        <p
          v-else
          class="text-xs leading-relaxed"
          :class="selected.notes ? 'text-slate-700 dark:text-slate-200' : 'italic text-slate-400'"
        >
          {{ selected.notes || 'Chưa có ghi chú.' }}
        </p>
        <div
          v-if="editingNotes && canEdit"
          class="mt-2 flex justify-end gap-1.5"
        >
          <button
            type="button"
            class="btn-ghost px-2 py-1 text-xs"
            @click="emit('cancel-notes')"
          >
            Huỷ
          </button>
          <button
            type="button"
            class="btn-primary px-2 py-1 text-xs"
            :disabled="notesForm.processing"
            @click="emit('save-notes')"
          >
            Lưu
          </button>
        </div>
      </div>
      <footer
        v-if="selected.is_external_link && canEdit"
        class="border-t border-slate-100 px-3 py-2 dark:border-slate-800"
      >
        <button
          v-if="!editingLink"
          type="button"
          class="w-full rounded border border-dashed border-slate-300 py-1.5 text-xs font-medium text-slate-600 hover:border-brand/40 hover:text-brand dark:border-slate-600"
          @click="emit('edit-link')"
        >
          Sửa link
        </button>
        <div
          v-else
          class="space-y-1.5"
        >
          <input
            :value="linkForm.title"
            type="text"
            class="input w-full text-xs"
            placeholder="Tên hiển thị"
            @input="emit('update:link-title', $event.target.value)"
          >
          <input
            :value="linkForm.external_url"
            type="url"
            class="input w-full text-xs"
            placeholder="https://docs.google.com/… hoặc https://…/file.pdf"
            @input="emit('update:link-url', $event.target.value)"
          >
          <div class="flex justify-end gap-1.5">
            <button
              type="button"
              class="btn-ghost px-2 py-1 text-xs"
              @click="emit('cancel-link')"
            >
              Huỷ
            </button>
            <button
              type="button"
              class="btn-primary px-2 py-1 text-xs"
              :disabled="linkForm.processing"
              @click="emit('save-link')"
            >
              Lưu
            </button>
          </div>
        </div>
      </footer>
      <footer
        v-else-if="canDelete && !selected.is_external_link"
        class="border-t border-slate-100 px-3 py-2 dark:border-slate-800"
      >
        <button
          type="button"
          class="w-full rounded border border-dashed border-slate-300 py-1.5 text-xs font-medium text-slate-600 hover:border-brand/40 hover:text-brand dark:border-slate-600"
          @click="emit('replace')"
        >
          Thay thế file
        </button>
      </footer>
    </section>

    <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-lg border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
      <header class="flex items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/90 px-3 py-1.5 dark:border-slate-800 dark:bg-slate-800/50">
        <h4 class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          Lịch sử
        </h4>
        <span
          v-if="activities.length"
          class="rounded-full bg-slate-200/80 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums dark:bg-slate-700"
        >{{ activities.length }}</span>
      </header>
      <ul
        v-if="activities.length"
        class="doc-audit-list max-h-[min(28vh,220px)] space-y-0 overflow-y-auto px-2 py-2"
      >
        <li
          v-for="(item, index) in activities"
          :key="item.id"
          class="doc-audit-item relative flex gap-2 pb-3 pl-0.5 last:pb-0"
          :class="index < activities.length - 1 ? 'doc-audit-item--line' : ''"
        >
          <span
            class="relative z-[1] grid h-6 w-6 shrink-0 place-items-center rounded-full ring-2 ring-white dark:ring-slate-900"
            :class="activityTone(item.event)"
          >
            <AppIcon
              :name="activityIcon(item.event)"
              :size="11"
            />
          </span>
          <div class="min-w-0 flex-1 pt-0.5">
            <p class="text-xs font-medium leading-snug text-slate-800 dark:text-slate-100">
              {{ item.description }}
            </p>
            <p class="mt-0.5 text-[11px] text-slate-500 sm:text-xs">
              {{ datetime(item.created_at) }}
              <span v-if="item.employee?.name"> · {{ item.employee.name }}</span>
            </p>
          </div>
        </li>
      </ul>
      <p
        v-else
        class="px-3 py-6 text-center text-xs text-slate-400"
      >
        Chưa có lịch sử chỉnh sửa.
      </p>
    </section>
  </div>
</template>

<style scoped>
.doc-audit-item--line::before {
    content: '';
    position: absolute;
    left: 0.75rem;
    top: 1.5rem;
    bottom: -0.25rem;
    width: 2px;
    background: linear-gradient(to bottom, rgb(226 232 240), rgb(226 232 240 / 0));
    border-radius: 1px;
}

:global(.dark) .doc-audit-item--line::before {
    background: linear-gradient(to bottom, rgb(51 65 85), rgb(51 65 85 / 0));
}
</style>
