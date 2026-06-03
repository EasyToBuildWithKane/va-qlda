<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    row: { type: Object, required: true },
    canReview: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'approve', 'reject', 'delete']);

const open = ref(false);
const triggerRef = ref(null);
const panelRef = ref(null);
const dropdownStyle = ref({});
const openUp = ref(false);

const MENU_MIN_WIDTH = 176;
const PANEL_MAX_H = 320;
const GAP = 4;
const Z_INDEX = 50;

function toggle() {
    open.value = !open.value;
}

function close() {
    open.value = false;
}

async function positionPanel() {
    await nextTick();
    const el = triggerRef.value;
    if (!el) return;

    const rect = el.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom - GAP;
    const spaceAbove = rect.top - GAP;
    openUp.value = spaceBelow < PANEL_MAX_H && spaceAbove > spaceBelow;

    const left = Math.max(8, Math.min(rect.right - MENU_MIN_WIDTH, window.innerWidth - MENU_MIN_WIDTH - 8));

    dropdownStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        minWidth: `${MENU_MIN_WIDTH}px`,
        zIndex: Z_INDEX,
        ...(openUp.value
            ? { bottom: `${window.innerHeight - rect.top + GAP}px` }
            : { top: `${rect.bottom + GAP}px` }),
    };
}

function onPointerDownOutside(e) {
    const t = e.target;
    if (triggerRef.value?.contains(t) || panelRef.value?.contains(t)) return;
    close();
}

let scrollListener = null;

watch(open, async (isOpen) => {
    if (isOpen) {
        await positionPanel();
        scrollListener = () => positionPanel();
        window.addEventListener('scroll', scrollListener, true);
        window.addEventListener('resize', scrollListener);
        document.addEventListener('mousedown', onPointerDownOutside);
    } else {
        if (scrollListener) {
            window.removeEventListener('scroll', scrollListener, true);
            window.removeEventListener('resize', scrollListener);
            scrollListener = null;
        }
        document.removeEventListener('mousedown', onPointerDownOutside);
    }
});

onBeforeUnmount(() => {
    if (scrollListener) {
        window.removeEventListener('scroll', scrollListener, true);
        window.removeEventListener('resize', scrollListener);
    }
    document.removeEventListener('mousedown', onPointerDownOutside);
});

function onEdit() {
    close();
    emit('edit', props.row);
}

function onApprove() {
    close();
    emit('approve', props.row);
}

function onReject() {
    close();
    emit('reject', props.row);
}

function onDelete() {
    close();
    emit('delete', props.row);
}

const hasExport = computed(() => Boolean(props.row.export_pdf_url || props.row.export_docx_url));

const hasMenuItems = computed(() =>
    hasExport.value
    || props.row.can_edit
    || (props.row.can_review && props.canReview)
    || props.row.can_delete);
</script>

<template>
  <div class="inline-flex justify-center">
    <button
      v-if="hasMenuItems"
      ref="triggerRef"
      type="button"
      class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
      :aria-expanded="open"
      aria-haspopup="menu"
      title="Thao tác"
      @click="toggle"
    >
      <AppIcon
        name="more-horizontal"
        :size="16"
      />
      <span class="hidden sm:inline">Thao tác</span>
      <AppIcon
        name="chevron-down"
        :size="12"
        class="opacity-50 transition-transform"
        :class="open && 'rotate-180'"
      />
    </button>
    <span
      v-else
      class="text-xs text-slate-300"
    >—</span>

    <Teleport to="body">
      <div
        v-if="open && hasMenuItems"
        ref="panelRef"
        :style="dropdownStyle"
        class="rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2"
        role="menu"
      >
        <template v-if="row.export_pdf_url || row.export_docx_url">
          <p class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">
            Xuất phiếu
          </p>
          <a
            v-if="row.export_pdf_url"
            :href="row.export_pdf_url"
            target="_blank"
            rel="noopener"
            class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
            role="menuitem"
            @click="close"
          >
            <AppIcon
              name="pdf"
              :size="15"
              class="text-brand"
            />
            Tải PDF
          </a>
          <a
            v-if="row.export_docx_url"
            :href="row.export_docx_url"
            target="_blank"
            rel="noopener"
            class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
            role="menuitem"
            @click="close"
          >
            <AppIcon
              name="download"
              :size="15"
            />
            Tải Word (.docx)
          </a>
        </template>

        <button
          v-if="row.can_edit"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
          role="menuitem"
          @click="onEdit"
        >
          <AppIcon
            name="edit"
            :size="15"
          />
          Chỉnh sửa
        </button>

        <template v-if="row.can_review && canReview">
          <div
            v-if="row.export_pdf_url || row.export_docx_url || row.can_edit"
            class="my-1 border-t border-slate-100"
          />
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-emerald-700 transition hover:bg-emerald-50"
            role="menuitem"
            @click="onApprove"
          >
            <AppIcon
              name="check"
              :size="15"
            />
            Duyệt phiếu
          </button>
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-700 transition hover:bg-rose-50"
            role="menuitem"
            @click="onReject"
          >
            <AppIcon
              name="close"
              :size="15"
            />
            Từ chối
          </button>
        </template>

        <div
          v-if="row.can_delete && (row.can_review && canReview || row.can_edit || row.export_pdf_url)"
          class="my-1 border-t border-slate-100"
        />

        <button
          v-if="row.can_delete"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-700 transition hover:bg-rose-50"
          role="menuitem"
          @click="onDelete"
        >
          <AppIcon
            name="delete"
            :size="15"
          />
          Xoá phiếu
        </button>
      </div>
    </Teleport>
  </div>
</template>
