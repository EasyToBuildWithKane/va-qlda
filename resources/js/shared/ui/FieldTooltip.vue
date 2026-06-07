<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    text: { type: String, required: true },
    /** Tooltip rộng hơn cho mô tả dài (form phức tạp). */
    wide: { type: Boolean, default: false },
});

const triggerRef = ref(null);
const tipRef = ref(null);
const open = ref(false);
const tipStyle = ref({});

async function positionTip() {
    await nextTick();
    await nextTick();
    const el = triggerRef.value;
    const tip = tipRef.value;
    if (!el || !tip) return;

    const rect = el.getBoundingClientRect();
    const tipRect = tip.getBoundingClientRect();
    const gap = 8;
    const pad = 12;
    const maxW = props.wide ? Math.min(window.innerWidth - pad * 2, 352) : 208;

    let left = rect.left + rect.width / 2 - maxW / 2;
    left = Math.max(pad, Math.min(left, window.innerWidth - maxW - pad));

    let top = rect.top - tipRect.height - gap;
    if (top < pad) {
        top = rect.bottom + gap;
    }

    tipStyle.value = {
        position: 'fixed',
        left: `${left}px`,
        top: `${top}px`,
        width: `${maxW}px`,
        zIndex: 200,
    };
}

function show() {
    open.value = true;
}

function hide() {
    open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        positionTip();
        window.addEventListener('scroll', positionTip, true);
        window.addEventListener('resize', positionTip);
    } else {
        window.removeEventListener('scroll', positionTip, true);
        window.removeEventListener('resize', positionTip);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', positionTip, true);
    window.removeEventListener('resize', positionTip);
});
</script>

<template>
  <span class="inline-flex align-middle">
    <button
      ref="triggerRef"
      type="button"
      class="grid h-4 w-4 place-items-center rounded-full text-slate-400 hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand/30"
      :aria-label="text"
      tabindex="0"
      @mouseenter="show"
      @mouseleave="hide"
      @focus="show"
      @blur="hide"
    >
      <AppIcon
        name="info"
        :size="14"
      />
    </button>
    <Teleport to="body">
      <span
        v-if="open"
        ref="tipRef"
        :style="tipStyle"
        class="pointer-events-none rounded-lg bg-slate-800 px-3 py-2 text-left text-xs font-normal leading-relaxed text-white shadow-elevation-2"
        role="tooltip"
      >
        {{ text }}
      </span>
    </Teleport>
  </span>
</template>
