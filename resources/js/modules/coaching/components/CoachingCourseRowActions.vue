<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    course: { type: Object, required: true },
});

const emit = defineEmits(['detail', 'edit', 'delete']);

const open = ref(false);
const triggerRef = ref(null);
const panelRef = ref(null);
const dropdownStyle = ref({});

const MENU_MIN_WIDTH = 192;
const PANEL_MAX_H = 220;
const GAP = 4;
const Z_INDEX = 50;

const canUpdate = computed(() => props.course.can?.update === true);
const canDelete = computed(() => props.course.can?.delete === true);

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
    const openUp = spaceBelow < PANEL_MAX_H && spaceAbove > spaceBelow;

    const left = Math.max(8, Math.min(rect.right - MENU_MIN_WIDTH, window.innerWidth - MENU_MIN_WIDTH - 8));

    dropdownStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        minWidth: `${MENU_MIN_WIDTH}px`,
        zIndex: Z_INDEX,
        ...(openUp
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

function run(emitName) {
    close();
    emit(emitName, props.course);
}
</script>

<template>
  <div class="relative flex justify-end">
    <button
      ref="triggerRef"
      type="button"
      class="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700"
      :class="open ? 'border-brand/30 text-brand' : ''"
      :aria-expanded="open"
      aria-haspopup="menu"
      :title="`Thao tác khóa ${course.code}`"
      :aria-label="`Thao tác khóa ${course.name}`"
      @click.stop="toggle"
    >
      <AppIcon
        name="more-horizontal"
        :size="16"
      />
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        ref="panelRef"
        :style="dropdownStyle"
        class="overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
        role="menu"
        :aria-label="`Menu thao tác khóa ${course.name}`"
      >
        <button
          type="button"
          role="menuitem"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
          @click.stop="run('detail')"
        >
          <AppIcon
            name="eye"
            :size="14"
            class="shrink-0"
          />
          Xem chi tiết
        </button>
        <button
          v-if="canUpdate"
          type="button"
          role="menuitem"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
          @click.stop="run('edit')"
        >
          <AppIcon
            name="edit"
            :size="14"
            class="shrink-0"
          />
          Sửa khóa
        </button>
        <button
          v-if="canDelete"
          type="button"
          role="menuitem"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-700 transition hover:bg-rose-50"
          @click.stop="run('delete')"
        >
          <AppIcon
            name="trash"
            :size="14"
            class="shrink-0"
          />
          Xóa khóa học
        </button>
      </div>
    </Teleport>
  </div>
</template>
