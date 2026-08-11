<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    row: { type: Object, required: true },
});

const emit = defineEmits(['edit', 'renew', 'delete']);

const open = ref(false);
const triggerRef = ref(null);
const panelRef = ref(null);
const dropdownStyle = ref({});
const openUp = ref(false);

const MENU_MIN_WIDTH = 168;
const PANEL_MAX_H = 200;
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
    } else if (scrollListener) {
        window.removeEventListener('scroll', scrollListener, true);
        window.removeEventListener('resize', scrollListener);
        scrollListener = null;
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

function onRenew() {
    close();
    emit('renew', props.row);
}

function onDelete() {
    close();
    emit('delete', props.row);
}

const hasMenuItems = computed(() => true);
</script>

<template>
  <div class="inline-flex justify-end">
    <button
      ref="triggerRef"
      type="button"
      class="inline-flex h-8 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
      :aria-expanded="open"
      aria-haspopup="menu"
      title="Thao tác"
      @click="toggle"
    >
      <AppIcon
        name="more"
        :size="14"
      />
      <span>Thao tác</span>
    </button>

    <Teleport to="body">
      <div
        v-if="open && hasMenuItems"
        ref="panelRef"
        role="menu"
        class="overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
        :style="dropdownStyle"
      >
        <button
          type="button"
          role="menuitem"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
          @click="onEdit"
        >
          <AppIcon
            name="edit"
            :size="14"
          />
          Sửa
        </button>
        <button
          v-if="row.can_renew"
          type="button"
          role="menuitem"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
          @click="onRenew"
        >
          <AppIcon
            name="refresh"
            :size="14"
          />
          Gia hạn
        </button>
        <button
          type="button"
          role="menuitem"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-700 hover:bg-rose-50"
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
