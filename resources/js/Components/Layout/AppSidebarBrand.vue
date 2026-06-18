<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    rail: { type: Boolean, default: false },
    appShortName: { type: String, default: 'VA' },
    appName: { type: String, default: '' },
    hideToggle: { type: Boolean, default: false },
    /** Hàng header drawer mobile — logo gọn, không viền dưới trùng wrapper */
    embedded: { type: Boolean, default: false },
});

const emit = defineEmits(['collapse', 'expand']);

const shellClass = computed(() => {
    if (props.rail) {
        return 'flex h-[4.25rem] justify-center px-0';
    }
    if (props.embedded) {
        return 'grid h-16 min-h-16 grid-cols-1 items-center border-0 px-0 py-0';
    }
    return [
        'grid min-h-[6.5rem] h-[7.5rem] items-center gap-0 border-b border-white/[0.10] px-0 py-0',
        props.hideToggle
            ? 'grid-cols-1'
            : 'grid-cols-[minmax(0,1fr)_2.5rem]',
    ].join(' ');
});

const wordmarkClass = computed(() => {
    if (props.embedded) {
        return 'max-h-14 w-auto max-w-full object-contain object-left drop-shadow-[0_2px_8px_rgba(0,0,0,0.22)]';
    }
    return 'max-h-[7rem] w-full max-w-full object-contain object-left drop-shadow-[0_2px_10px_rgba(0,0,0,0.28)]';
});

const linkAlignClass = computed(() => (props.embedded ? 'justify-start pl-3' : 'justify-start'));
</script>

<template>
  <div
    class="relative shrink-0"
    :class="shellClass"
  >
    <template v-if="rail">
      <button
        type="button"
        class="group grid h-11 w-11 min-h-[2.75rem] min-w-[2.75rem] place-items-center rounded-xl bg-white/10 p-1.5 text-white transition-colors hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/50 active:scale-[0.98]"
        :title="appName"
        aria-label="Mở rộng thanh bên"
        @click="emit('expand')"
      >
        <img
          src="/images/congnghe/brand/vas-badge-circle.png"
          alt=""
          class="h-full w-full object-contain drop-shadow-[0_1px_3px_rgba(0,0,0,0.35)]"
        >
      </button>
    </template>
    <template v-else>
      <Link
        href="/dashboard"
        :class="[
          'flex min-h-0 min-w-0 items-center overflow-hidden py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-2 focus-visible:ring-offset-brand rounded-md',
          linkAlignClass,
          embedded ? 'pr-2 pl-2' : 'px-2',
        ]"
        aria-label="VAschools — Bảng điều khiển"
      >
        <img
          src="/images/congnghe/brand/vas-white.png"
          alt="VAschools"
          :class="wordmarkClass"
          width="272"
          height="84"
          decoding="async"
        >
      </Link>
      <button
        v-if="!hideToggle"
        type="button"
        class="mr-0.5 grid h-9 w-9 min-h-[2.25rem] min-w-[2.25rem] place-items-center self-center rounded-lg text-brand-100/55 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 active:scale-[0.98]"
        title="Thu gọn thanh bên"
        aria-label="Thu gọn thanh bên"
        @click="emit('collapse')"
      >
        <AppIcon
          name="collapse-left"
          :size="17"
        />
      </button>
    </template>
  </div>
</template>
