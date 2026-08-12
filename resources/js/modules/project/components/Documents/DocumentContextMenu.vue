<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    x: { type: Number, default: 0 },
    y: { type: Number, default: 0 },
    items: { type: Array, default: () => [] },
});

const emit = defineEmits(['select', 'close']);

const activeIndex = ref(0);

const visibleItems = computed(() => {
    const raw = props.items.filter((item) => item && !item.hidden);
    const cleaned = [];
    raw.forEach((item) => {
        if (item.type === 'separator') {
            if (!cleaned.length || cleaned[cleaned.length - 1].type === 'separator') return;
            cleaned.push(item);
            return;
        }
        cleaned.push(item);
    });
    while (cleaned.length && cleaned[cleaned.length - 1].type === 'separator') cleaned.pop();
    return cleaned;
});

const actionableIndices = computed(() => visibleItems.value
    .map((item, index) => ({ item, index }))
    .filter(({ item }) => item.type !== 'separator' && !item.disabled)
    .map(({ index }) => index));

const positionStyle = computed(() => {
    const pad = 8;
    const menuW = 220;
    const menuH = Math.max(40, visibleItems.value.length * 36 + 8);
    let left = props.x;
    let top = props.y;
    if (typeof window !== 'undefined') {
        left = Math.min(left, window.innerWidth - menuW - pad);
        top = Math.min(top, window.innerHeight - menuH - pad);
        left = Math.max(pad, left);
        top = Math.max(pad, top);
    }
    return {
        left: `${left}px`,
        top: `${top}px`,
        width: `${menuW}px`,
    };
});

const moveActive = (delta) => {
    const indices = actionableIndices.value;
    if (!indices.length) return;
    const currentPos = indices.indexOf(activeIndex.value);
    let nextPos = currentPos < 0 ? 0 : currentPos + delta;
    if (nextPos < 0) nextPos = indices.length - 1;
    if (nextPos >= indices.length) nextPos = 0;
    activeIndex.value = indices[nextPos];
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
        return;
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        moveActive(1);
        return;
    }
    if (event.key === 'ArrowUp') {
        event.preventDefault();
        moveActive(-1);
        return;
    }
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        const item = visibleItems.value[activeIndex.value];
        if (item && item.type !== 'separator' && !item.disabled) {
            onSelect(item);
        }
    }
};

const onPointerDown = (event) => {
    const el = document.getElementById('project-doc-context-menu');
    if (el?.contains(event.target)) return;
    emit('close');
};

const onScrollClose = () => emit('close');

watch(() => props.open, async (open) => {
    if (open) {
        activeIndex.value = actionableIndices.value[0] ?? 0;
        await nextTick();
        document.addEventListener('keydown', onKeydown);
        document.addEventListener('mousedown', onPointerDown, true);
        document.addEventListener('scroll', onScrollClose, true);
    } else {
        document.removeEventListener('keydown', onKeydown);
        document.removeEventListener('mousedown', onPointerDown, true);
        document.removeEventListener('scroll', onScrollClose, true);
    }
});

watch(visibleItems, () => {
    if (!actionableIndices.value.includes(activeIndex.value)) {
        activeIndex.value = actionableIndices.value[0] ?? 0;
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('mousedown', onPointerDown, true);
    document.removeEventListener('scroll', onScrollClose, true);
});

const onSelect = (item) => {
    if (item.disabled || item.type === 'separator') return;
    emit('select', item.key);
    emit('close');
};
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && visibleItems.length"
      id="project-doc-context-menu"
      role="menu"
      class="fixed z-[300] overflow-hidden rounded-xl border border-slate-200/90 bg-white py-1 shadow-lg shadow-slate-900/10 dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/40"
      :style="positionStyle"
      @contextmenu.prevent
    >
      <template
        v-for="(item, index) in visibleItems"
        :key="item.key || `sep-${index}`"
      >
        <div
          v-if="item.type === 'separator'"
          class="my-1 border-t border-slate-100 dark:border-slate-800"
          role="separator"
        />
        <button
          v-else
          type="button"
          role="menuitem"
          class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition"
          :class="[
            item.disabled
              ? 'cursor-not-allowed text-slate-300 dark:text-slate-600'
              : item.danger
                ? 'text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40'
                : 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800',
            !item.disabled && activeIndex === index
              ? (item.danger ? 'bg-rose-50 dark:bg-rose-950/40' : 'bg-slate-50 dark:bg-slate-800')
              : '',
          ]"
          :disabled="item.disabled"
          @mouseenter="activeIndex = index"
          @click="onSelect(item)"
        >
          <AppIcon
            :name="item.icon"
            :size="15"
            class="shrink-0 opacity-80"
          />
          <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
        </button>
      </template>
    </div>
  </Teleport>
</template>
