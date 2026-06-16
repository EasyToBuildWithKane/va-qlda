<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    grant: { type: Object, required: true },
});

const emit = defineEmits(['edit', 'revoke']);

const open = ref(false);
const triggerRef = ref(null);
const panelRef = ref(null);
const dropdownStyle = ref({});
const openUp = ref(false);

const MENU_MIN_WIDTH = 168;
const PANEL_MAX_H = 120;
const GAP = 4;

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
        zIndex: 50,
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

const accountLabel = computed(
    () => props.grant.account?.display_name || 'Người dùng',
);
</script>

<template>
  <div class="relative inline-flex">
    <button
      ref="triggerRef"
      type="button"
      class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 transition hover:bg-slate-50"
      :aria-expanded="open"
      aria-haspopup="menu"
      :aria-label="`Thao tác quyền — ${accountLabel}`"
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
        class="rounded-xl border border-slate-200/90 bg-white py-1 shadow-elevation-2"
        :style="dropdownStyle"
        role="menu"
      >
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
          role="menuitem"
          @click="emit('edit', grant); close()"
        >
          <AppIcon
            name="edit"
            :size="15"
          />
          Chỉnh sửa quyền
        </button>
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50/80"
          role="menuitem"
          @click="emit('revoke', grant); close()"
        >
          <AppIcon
            name="delete"
            :size="15"
          />
          Thu hồi quyền
        </button>
      </div>
    </Teleport>
  </div>
</template>
