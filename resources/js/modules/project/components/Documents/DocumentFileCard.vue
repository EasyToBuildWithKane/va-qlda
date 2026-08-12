<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    name: { type: String, required: true },
    meta: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    url: { type: String, default: null },
    isImage: { type: Boolean, default: false },
    isPdf: { type: Boolean, default: false },
    isLink: { type: Boolean, default: false },
    badge: { type: String, default: 'FILE' },
    previewSnippet: { type: String, default: null },
    active: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    showActions: { type: Boolean, default: true },
    canDrag: { type: Boolean, default: false },
});

const emit = defineEmits(['click', 'preview', 'download', 'details', 'delete', 'rename', 'drag-start', 'drag-end']);

const rootRef = ref(null);
const pdfInView = ref(false);
let observer = null;

const kind = computed(() => {
    const b = (props.badge || '').toUpperCase();
    if (props.isImage || ['PNG', 'JPG', 'JPEG', 'GIF', 'WEBP', 'SVG', 'IMG'].includes(b)) return 'image';
    if (props.isPdf || b === 'PDF') return 'pdf';
    if (['ZIP', 'RAR', '7Z'].includes(b)) return 'zip';
    if (['XLS', 'XLSX', 'CSV', 'SHT'].includes(b)) return 'sheet';
    if (['DOC', 'DOCX'].includes(b)) return 'doc';
    if (props.isLink || b === 'LINK') return 'link';
    if (props.previewSnippet || ['TXT', 'MD', 'HTML', 'HTM'].includes(b)) return 'text';
    return 'file';
});

const footerIcon = computed(() => {
    if (kind.value === 'pdf') return 'pdf';
    if (kind.value === 'zip') return 'folder';
    if (kind.value === 'image') return 'image';
    if (kind.value === 'link') return 'link';
    return 'documents';
});

const footerIconClass = computed(() => ({
    image: 'text-sky-500',
    pdf: 'text-rose-500',
    zip: 'text-amber-500',
    sheet: 'text-emerald-600',
    doc: 'text-sky-500',
    link: 'text-teal-600',
    text: 'text-sky-500',
    file: 'text-sky-500',
}[kind.value]));

const pdfPreviewUrl = computed(() => {
    if (!props.url) return '';
    const base = props.url.split('#')[0];
    return `${base}#page=1&view=FitH&toolbar=0&navpanes=0`;
});

const snippetLines = computed(() => {
    if (!props.previewSnippet) return [];
    return String(props.previewSnippet).split('\n').slice(0, 8);
});

onMounted(() => {
    if (kind.value !== 'pdf' || !props.url || typeof IntersectionObserver === 'undefined') {
        if (kind.value === 'pdf' && props.url) pdfInView.value = true;
        return;
    }
    observer = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) {
            pdfInView.value = true;
            observer?.disconnect();
            observer = null;
        }
    }, { rootMargin: '120px' });
    if (rootRef.value) observer.observe(rootRef.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
});

const onAction = (event, type) => {
    event.stopPropagation();
    emit(type);
};
</script>

<template>
  <div
    ref="rootRef"
    class="doc-file-card group relative flex w-full min-w-0 flex-col overflow-hidden rounded-xl border bg-white text-left transition duration-150 dark:bg-slate-900"
    :class="[
      active
        ? 'border-brand/40 ring-2 ring-brand/20 dark:border-brand/50'
        : 'border-slate-200/90 hover:border-slate-300 hover:shadow-sm dark:border-slate-700 dark:hover:border-slate-600',
      canDrag ? 'cursor-grab active:cursor-grabbing' : '',
    ]"
    :draggable="canDrag"
    @dragstart="canDrag && emit('drag-start', $event)"
    @dragend="emit('drag-end')"
  >
    <button
      type="button"
      class="relative flex aspect-[4/3] w-full items-center justify-center overflow-hidden bg-[#F3F5FA] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand/40 dark:bg-slate-950/50"
      @click="$emit('click')"
    >
      <img
        v-if="kind === 'image' && url"
        :src="url"
        :alt="name"
        class="h-full w-full object-cover"
      >
      <div
        v-else-if="kind === 'pdf' && url"
        class="doc-file-card__pdf pointer-events-none absolute inset-2 overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-black/5 dark:bg-slate-900"
      >
        <iframe
          v-if="pdfInView"
          :src="pdfPreviewUrl"
          class="doc-file-card__pdf-iframe"
          title="Xem trước trang đầu PDF"
          tabindex="-1"
        />
        <div
          v-else
          class="flex h-full flex-col gap-1.5 px-2.5 py-2"
        >
          <span class="h-1.5 w-[78%] rounded-full bg-slate-200 dark:bg-slate-700" />
          <span class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800" />
          <span class="h-1.5 w-[55%] rounded-full bg-slate-100 dark:bg-slate-800" />
        </div>
        <span class="pointer-events-none absolute bottom-1.5 left-1.5 rounded bg-rose-500 px-1 py-0.5 text-[8px] font-bold text-white">
          PDF
        </span>
      </div>
      <div
        v-else-if="kind === 'text' && snippetLines.length"
        class="pointer-events-none absolute inset-2 overflow-hidden rounded-md bg-white px-2.5 py-2 text-left shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-950 dark:ring-slate-700"
      >
        <pre class="whitespace-pre-wrap break-words font-mono text-[9px] leading-snug text-slate-600 dark:text-slate-300">{{ snippetLines.join('\n') }}</pre>
      </div>
      <div
        v-else-if="kind === 'zip'"
        class="flex flex-col items-center"
      >
        <span class="relative grid h-[4.5rem] w-[4.5rem] place-items-center">
          <span class="absolute inset-0 rounded-2xl bg-gradient-to-br from-amber-300 via-amber-400 to-orange-500 shadow-md" />
          <span class="absolute inset-x-3 top-2.5 h-1 rounded-full bg-white/35" />
          <span class="absolute inset-y-4 left-1/2 w-0.5 -translate-x-1/2 bg-slate-800/25" />
          <AppIcon
            name="folder"
            :size="30"
            class="relative text-white"
          />
        </span>
      </div>
      <div
        v-else
        class="grid h-14 w-14 place-items-center rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-900 dark:ring-slate-700"
      >
        <AppIcon
          :name="footerIcon"
          :size="26"
          :class="footerIconClass"
        />
      </div>

      <div
        v-if="showActions"
        class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center gap-1 bg-gradient-to-t from-slate-900/40 to-transparent px-2 pb-2 pt-8 opacity-0 transition group-hover:pointer-events-auto group-hover:opacity-100 group-focus-within:pointer-events-auto group-focus-within:opacity-100"
      >
        <button
          v-if="url"
          type="button"
          class="grid h-7 w-7 place-items-center rounded-md bg-white/95 text-slate-600 shadow-sm hover:text-brand"
          title="Xem"
          @click="onAction($event, 'preview')"
        >
          <AppIcon
            name="eye"
            :size="13"
          />
        </button>
        <button
          v-if="canEdit"
          type="button"
          class="grid h-7 w-7 place-items-center rounded-md bg-white/95 text-slate-600 shadow-sm hover:text-brand"
          title="Đổi tên"
          @click="onAction($event, 'rename')"
        >
          <AppIcon
            name="edit"
            :size="13"
          />
        </button>
        <a
          v-if="url && !isLink"
          :href="url"
          download
          class="grid h-7 w-7 place-items-center rounded-md bg-white/95 text-slate-600 shadow-sm hover:text-brand"
          title="Tải xuống"
          @click.stop
        >
          <AppIcon
            name="download"
            :size="13"
          />
        </a>
        <button
          type="button"
          class="grid h-7 w-7 place-items-center rounded-md bg-white/95 text-slate-600 shadow-sm hover:text-brand"
          title="Chi tiết"
          @click="onAction($event, 'details')"
        >
          <AppIcon
            name="info"
            :size="13"
          />
        </button>
        <button
          v-if="canDelete"
          type="button"
          class="grid h-7 w-7 place-items-center rounded-md bg-white/95 text-rose-500 shadow-sm hover:bg-rose-50"
          title="Xoá"
          @click="onAction($event, 'delete')"
        >
          <AppIcon
            name="delete"
            :size="13"
          />
        </button>
      </div>
    </button>

    <button
      type="button"
      class="flex min-w-0 items-start gap-2 bg-white px-2.5 py-2.5 text-left dark:bg-slate-900"
      @click="$emit('click')"
    >
      <AppIcon
        :name="footerIcon"
        :size="16"
        class="mt-0.5 shrink-0"
        :class="footerIconClass"
      />
      <span class="min-w-0 flex-1">
        <span
          class="block truncate text-xs font-semibold text-slate-800 dark:text-slate-100"
          :title="name"
        >
          {{ name }}
        </span>
        <span
          v-if="meta"
          class="mt-0.5 block truncate text-[11px] tabular-nums text-slate-500 dark:text-slate-400"
        >
          {{ meta }}
        </span>
        <span
          v-if="subtitle"
          class="mt-1 inline-flex max-w-full items-center gap-1 truncate text-[10px] font-medium text-slate-400"
          :title="subtitle"
        >
          <AppIcon
            name="task"
            :size="10"
            class="shrink-0 text-brand/70"
          />
          <span class="truncate">{{ subtitle }}</span>
        </span>
      </span>
    </button>
  </div>
</template>

<style scoped>
.doc-file-card__pdf {
    display: block;
}

.doc-file-card__pdf-iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 180%;
    height: 180%;
    border: 0;
    transform: scale(0.555);
    transform-origin: top left;
    background: #fff;
}
</style>
