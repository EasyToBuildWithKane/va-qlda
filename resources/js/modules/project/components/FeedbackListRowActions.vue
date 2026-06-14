<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    feedback: { type: Object, required: true },
});

const emit = defineEmits(['remove']);

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

const projectHref = computed(() => {
    const id = props.feedback.project?.id ?? props.feedback.project_id;
    if (!id) return null;
    return `/projects/${id}?tab=feedback`;
});

function onRemove() {
    close();
    emit('remove', props.feedback);
}
</script>

<template>
  <div class="inline-flex justify-end">
    <button
      ref="triggerRef"
      type="button"
      class="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700"
      :class="open ? 'border-brand/30 text-brand' : ''"
      :aria-expanded="open"
      aria-haspopup="menu"
      aria-label="Thao tác"
      title="Thao tác"
      @click.stop="toggle"
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
        class="rounded-xl border border-slate-200 bg-white py-1 shadow-elevation-2"
        role="menu"
      >
        <Link
          :href="`/feedback/${feedback.id}`"
          class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
          role="menuitem"
          preserve-scroll
          @click="close"
        >
          <AppIcon
            name="eye"
            :size="15"
          />
          Xem chi tiết
        </Link>
        <Link
          v-if="projectHref"
          :href="projectHref"
          class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
          role="menuitem"
          preserve-scroll
          @click="close"
        >
          <AppIcon
            name="projects"
            :size="15"
          />
          Mở tab dự án
        </Link>
        <div
          v-if="feedback.can?.delete"
          class="my-1 border-t border-slate-100"
        />
        <button
          v-if="feedback.can?.delete"
          type="button"
          class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-rose-700 transition hover:bg-rose-50"
          role="menuitem"
          @click="onRemove"
        >
          <AppIcon
            name="delete"
            :size="15"
          />
          Xoá phản hồi
        </button>
      </div>
    </Teleport>
  </div>
</template>
