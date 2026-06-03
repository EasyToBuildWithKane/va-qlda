<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    row: { type: Object, required: true },
    canManagePasswordViewers: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'renew', 'delete', 'password-viewers']);

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

function onRenew() {
    close();
    emit('renew', props.row);
}

function onDelete() {
    close();
    emit('delete', props.row);
}

function onPasswordViewers() {
    close();
    emit('password-viewers', props.row);
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

    <Teleport to="body">
      <div
        v-if="open && hasMenuItems"
        ref="panelRef"
        :style="dropdownStyle"
        class="rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2"
        role="menu"
      >
        <Link
          v-if="row.proposal_url"
          :href="row.proposal_url"
          class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
          role="menuitem"
          preserve-scroll
          @click="close"
        >
          <AppIcon
            name="performance"
            :size="15"
            class="text-brand"
          />
          Chi tiết phiếu đề xuất
          <span
            v-if="row.proposal_code"
            class="ml-auto font-mono text-[10px] text-slate-400"
          >{{ row.proposal_code }}</span>
        </Link>
        <button
          v-if="canManagePasswordViewers"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
          role="menuitem"
          @click="onPasswordViewers"
        >
          <AppIcon
            name="eye"
            :size="15"
          />
          Quyền xem MK (công cụ này)
        </button>
        <div
          v-if="row.proposal_url || canManagePasswordViewers"
          class="my-1 border-t border-slate-100"
        />
        <button
          v-if="row.can_renew"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-brand transition hover:bg-brand/5"
          role="menuitem"
          @click="onRenew"
        >
          <AppIcon
            name="refresh"
            :size="15"
          />
          Gia hạn
        </button>
        <button
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
        <div class="my-1 border-t border-slate-100" />
        <button
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-700 transition hover:bg-rose-50"
          role="menuitem"
          @click="onDelete"
        >
          <AppIcon
            name="delete"
            :size="15"
          />
          Xoá tài khoản
        </button>
      </div>
    </Teleport>
  </div>
</template>
