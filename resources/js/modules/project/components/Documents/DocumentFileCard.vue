<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    name: { type: String, required: true },
    dateLabel: { type: String, default: '' },
    sizeLabel: { type: String, default: '' },
    /** @deprecated prefer dateLabel + sizeLabel */
    meta: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    url: { type: String, default: null },
    isImage: { type: Boolean, default: false },
    isPdf: { type: Boolean, default: false },
    isLink: { type: Boolean, default: false },
    badge: { type: String, default: 'FILE' },
    previewSnippet: { type: String, default: null },
    active: { type: Boolean, default: false },
    canDrag: { type: Boolean, default: false },
    selectable: { type: Boolean, default: false },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['click', 'contextmenu', 'toggle-select', 'drag-start', 'drag-end']);

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

const showDate = computed(() => props.dateLabel || '');
const showSize = computed(() => props.sizeLabel || '');
const legacyMeta = computed(() => (!showDate.value && !showSize.value ? props.meta : ''));

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
</script>

<template>
  <div
    ref="rootRef"
    class="doc-file-card group relative flex w-full min-w-0 flex-col overflow-hidden rounded-xl border bg-white text-left transition duration-150 dark:bg-slate-900"
    :class="[
      active || selected
        ? 'border-brand/40 ring-2 ring-brand/20 dark:border-brand/50'
        : 'border-slate-200/90 hover:border-slate-300 hover:shadow-sm dark:border-slate-700 dark:hover:border-slate-600',
      canDrag ? 'cursor-grab active:cursor-grabbing' : '',
    ]"
    :draggable="canDrag"
    @dragstart="canDrag && emit('drag-start', $event)"
    @dragend="emit('drag-end')"
    @contextmenu.prevent="emit('contextmenu', $event)"
  >
    <label
      v-if="selectable"
      class="absolute left-2 top-2 z-10 grid h-5 w-5 cursor-pointer place-items-center rounded bg-white/90 opacity-0 shadow-sm ring-1 ring-slate-200/80 transition group-hover:opacity-100 focus-within:opacity-100 dark:bg-slate-900/90 dark:ring-slate-700"
      :class="selected ? '!opacity-100' : ''"
      @click.stop
    >
      <input
        type="checkbox"
        class="h-3.5 w-3.5 rounded border-slate-300 text-brand focus:ring-brand/30"
        :checked="selected"
        :aria-label="`Chọn ${name}`"
        @change="emit('toggle-select')"
      >
    </label>

    <button
      type="button"
      class="relative flex aspect-[4/3] w-full items-center justify-center overflow-hidden bg-[#F3F5FA] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand/40 dark:bg-slate-950/50"
      @click="emit('click')"
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
    </button>

    <button
      type="button"
      class="flex min-w-0 items-start gap-2 bg-white px-2.5 py-2.5 text-left dark:bg-slate-900"
      @click="emit('click')"
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
          v-if="showDate || showSize"
          class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11px] tabular-nums text-slate-500 dark:text-slate-400"
        >
          <span v-if="showDate">{{ showDate }}</span>
          <span
            v-if="showDate && showSize"
            class="text-slate-300 dark:text-slate-600"
            aria-hidden="true"
          >·</span>
          <span v-if="showSize">{{ showSize }}</span>
        </span>
        <span
          v-else-if="legacyMeta"
          class="mt-0.5 block truncate text-[11px] tabular-nums text-slate-500 dark:text-slate-400"
        >
          {{ legacyMeta }}
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
