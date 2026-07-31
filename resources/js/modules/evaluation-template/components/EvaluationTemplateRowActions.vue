<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    template: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'delete']);

const open = ref(false);
const triggerRef = ref(null);
const panelRef = ref(null);
const dropdownStyle = ref({});
const openUp = ref(false);

const MENU_MIN_WIDTH = 176;
const PANEL_MAX_H = 220;
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

const showHref = computed(() => route('workspace.evaluation-templates.show', props.template.id));

function onEdit() {
    close();
    emit('edit', props.template);
}

function onDelete() {
    close();
    emit('delete', props.template);
}

function onDuplicate() {
    close();
    router.post(route('workspace.evaluation-templates.duplicate', props.template.id), {}, {
        preserveScroll: true,
    });
}
</script>

<template>
  <div class="inline-flex justify-end">
    <button
      ref="triggerRef"
      type="button"
      class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
      :aria-expanded="open"
      aria-haspopup="menu"
      aria-label="Thao tác mẫu đánh giá"
      @click="toggle"
    >
      <AppIcon
        name="more-vertical"
        :size="16"
      />
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        ref="panelRef"
        :style="dropdownStyle"
        class="overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2"
        role="menu"
      >
        <Link
          :href="showHref"
          class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
          role="menuitem"
          @click="close"
        >
          <AppIcon
            name="eye"
            :size="14"
          />
          Xem chi tiết
        </Link>
        <button
          v-if="canManage"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
          role="menuitem"
          @click="onEdit"
        >
          <AppIcon
            name="edit"
            :size="14"
          />
          Sửa
        </button>
        <button
          v-if="canManage"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
          role="menuitem"
          @click="onDuplicate"
        >
          <AppIcon
            name="copy"
            :size="14"
          />
          Nhân bản
        </button>
        <button
          v-if="canManage"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50"
          role="menuitem"
          @click="onDelete"
        >
          <AppIcon
            name="trash"
            :size="14"
          />
          Xóa
        </button>
      </div>
    </Teleport>
  </div>
</template>
