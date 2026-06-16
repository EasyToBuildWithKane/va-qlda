<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    /** left | right | top */
    placement: { type: String, default: 'top' },
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
    const maxW = props.wide ? Math.min(window.innerWidth - pad * 2, 280) : 220;

    let left = rect.left + rect.width / 2 - maxW / 2;
    left = Math.max(pad, Math.min(left, window.innerWidth - maxW - pad));

    let top = rect.top - tipRect.height - gap;
    if (props.placement === 'left') {
        left = rect.left - tipRect.width - gap;
        top = rect.top + rect.height / 2 - tipRect.height / 2;
        left = Math.max(pad, left);
    } else if (props.placement === 'right') {
        left = rect.right + gap;
        top = rect.top + rect.height / 2 - tipRect.height / 2;
        if (left + maxW > window.innerWidth - pad) {
            left = rect.left - tipRect.width - gap;
        }
    } else if (top < pad) {
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
  <span
    ref="triggerRef"
    class="inline-flex"
    @mouseenter="show"
    @mouseleave="hide"
    @focusin="show"
    @focusout="hide"
  >
    <slot />
    <Teleport to="body">
      <span
        v-if="open"
        ref="tipRef"
        :style="tipStyle"
        class="pointer-events-none rounded-lg bg-slate-800 px-2.5 py-1.5 text-left text-xs font-normal leading-snug text-white shadow-elevation-2"
        role="tooltip"
      >
        {{ label }}
      </span>
    </Teleport>
  </span>
</template>
