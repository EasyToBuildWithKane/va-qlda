<script setup>
import { onBeforeUnmount, ref } from 'vue';

defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: String, required: true },
    ariaLabel: { type: String, default: 'Loại tiêu chí' },
});

const emit = defineEmits(['update:modelValue']);

const scrollerRef = ref(null);
const dragging = ref(false);

let pointerId = null;
let startX = 0;
let startScrollLeft = 0;
let moved = false;
let suppressClick = false;
const DRAG_THRESHOLD = 12;

function onPointerDown(e) {
    const el = scrollerRef.value;
    if (!el || e.button !== 0) return;
    // Touch / pen: native scroll
    if (e.pointerType !== 'mouse') return;

    pointerId = e.pointerId;
    startX = e.clientX;
    startScrollLeft = el.scrollLeft;
    moved = false;
    suppressClick = false;
    dragging.value = false;
    // Không setPointerCapture ngay — để click tab hoạt động bình thường
}

function onPointerMove(e) {
    const el = scrollerRef.value;
    if (!el || pointerId !== e.pointerId) return;

    const dx = e.clientX - startX;
    if (!moved && Math.abs(dx) < DRAG_THRESHOLD) return;

    if (!moved) {
        moved = true;
        suppressClick = true;
        dragging.value = true;
        try {
            el.setPointerCapture(e.pointerId);
        } catch {
            /* ignore */
        }
    }

    el.scrollLeft = startScrollLeft - dx;
    e.preventDefault();
}

function endPointer(e) {
    const el = scrollerRef.value;
    if (pointerId == null || (e?.pointerId != null && pointerId !== e.pointerId)) return;

    if (el?.hasPointerCapture?.(pointerId)) {
        try {
            el.releasePointerCapture(pointerId);
        } catch {
            /* ignore */
        }
    }

    const wasDragging = suppressClick;
    pointerId = null;
    dragging.value = false;
    moved = false;

    if (wasDragging) {
        // Chặn click tổng hợp sau khi kéo
        setTimeout(() => {
            suppressClick = false;
        }, 0);
    } else {
        suppressClick = false;
    }
}

function onTabClick(tabKey, e) {
    if (suppressClick) {
        e.preventDefault();
        e.stopPropagation();
        return;
    }
    emit('update:modelValue', tabKey);
}

onBeforeUnmount(() => {
    pointerId = null;
});
</script>

<template>
  <div
    ref="scrollerRef"
    class="eval-cat-tabs"
    :class="{ 'eval-cat-tabs--dragging': dragging }"
    role="tablist"
    :aria-label="ariaLabel"
    @pointerdown="onPointerDown"
    @pointermove="onPointerMove"
    @pointerup="endPointer"
    @pointercancel="endPointer"
    @lostpointercapture="endPointer"
  >
    <button
      v-for="tab in tabs"
      :key="tab.key"
      type="button"
      role="tab"
      class="eval-cat-tabs__tab"
      :class="modelValue === tab.key ? 'eval-cat-tabs__tab--active' : ''"
      :aria-selected="modelValue === tab.key"
      @click.stop="onTabClick(tab.key, $event)"
    >
      <span class="eval-cat-tabs__label">{{ tab.label }}</span>
      <span class="eval-cat-tabs__count">{{ tab.count }}</span>
    </button>
  </div>
</template>

<style scoped>
.eval-cat-tabs {
  display: flex;
  gap: 0.25rem;
  width: 100%;
  min-width: 0;
  max-width: 100%;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  -webkit-overflow-scrolling: touch;
  cursor: grab;
  border-bottom: 1px solid rgb(226 232 240);
  padding-inline: 0.25rem;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.eval-cat-tabs::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
}

.eval-cat-tabs--dragging {
  cursor: grabbing;
  user-select: none;
}

.eval-cat-tabs--dragging .eval-cat-tabs__tab {
  pointer-events: none;
}

.eval-cat-tabs__tab {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 0.375rem;
  max-width: min(18rem, 72vw);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.25;
  color: rgb(100 116 139);
  transition: color 0.15s ease, border-color 0.15s ease, background-color 0.15s ease;
  border-radius: 0.5rem 0.5rem 0 0;
  cursor: pointer;
}

.eval-cat-tabs__tab:hover {
  color: rgb(51 65 85);
  background-color: rgb(248 250 252);
}

.eval-cat-tabs__tab:focus-visible {
  outline: 2px solid rgb(154 0 54 / 0.35);
  outline-offset: 2px;
}

.eval-cat-tabs__tab--active {
  border-bottom-color: #9a0036;
  color: #9a0036;
  background-color: rgb(154 0 54 / 0.04);
}

.eval-cat-tabs__label {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.eval-cat-tabs__count {
  flex-shrink: 0;
  border-radius: 9999px;
  padding: 0.125rem 0.375rem;
  font-size: 0.625rem;
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
  background-color: rgb(241 245 249);
  color: rgb(100 116 139);
}

.eval-cat-tabs__tab--active .eval-cat-tabs__count {
  background-color: rgb(154 0 54 / 0.1);
  color: #9a0036;
}

@media (min-width: 640px) {
  .eval-cat-tabs {
    padding-inline: 0.5rem;
  }

  .eval-cat-tabs__tab {
    max-width: min(22rem, 40vw);
    padding: 0.625rem 0.875rem;
  }
}
</style>
