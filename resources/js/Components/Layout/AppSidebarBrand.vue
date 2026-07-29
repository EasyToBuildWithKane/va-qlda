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
        return 'flex h-[4.25rem] items-center justify-center border-b border-white/10 px-0';
    }
    if (props.embedded) {
        return 'grid h-16 min-h-16 grid-cols-1 items-center border-0 px-0 py-0';
    }
    // Expanded desktop — wordmark lớn (parity VA-HRM / brand trước)
    return 'relative grid min-h-[7rem] h-[8.25rem] grid-cols-1 items-center gap-0 border-b border-white/10 px-0 py-0';
});

const wordmarkClass = computed(() => {
    if (props.embedded) {
        return 'max-h-14 w-auto max-w-full object-contain object-left drop-shadow-[0_2px_8px_rgba(0,0,0,0.22)]';
    }
    return 'max-h-[8.25rem] w-[92%] max-w-[15.5rem] object-contain object-center drop-shadow-[0_2px_12px_rgba(0,0,0,0.32)]';
});

const linkAlignClass = computed(() => (props.embedded ? 'justify-start pl-3' : 'justify-center'));
</script>

<template>
  <div
    class="relative shrink-0"
    :class="shellClass"
  >
    <template v-if="rail">
      <button
        type="button"
        class="group flex w-full min-w-0 items-center justify-center px-2 py-1 text-white transition-transform duration-200 hover:scale-[1.02] focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/50 focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-sidebar)] active:scale-[0.98]"
        :title="appName"
        aria-label="Mở rộng thanh bên"
        @click="emit('expand')"
      >
        <span
          class="relative flex h-10 w-10 items-center justify-center rounded-lg bg-white/[0.08] p-1.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.12)] ring-1 ring-inset ring-white/15"
        >
          <img
            src="/images/congnghe/brand/vas-white-mark.png"
            srcset="/images/congnghe/brand/vas-white-mark.png 1x, /images/congnghe/brand/vas-white-mark@2x.png 2x"
            alt="VAschools"
            class="sidebar-brand-logo h-7 w-7 select-none object-contain"
            width="56"
            height="56"
            decoding="async"
            draggable="false"
          >
        </span>
      </button>
    </template>
    <template v-else>
      <Link
        href="/dashboard"
        preserve-scroll
        :class="[
          'flex min-h-0 min-w-0 items-center overflow-hidden py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-sidebar)] rounded-md',
          linkAlignClass,
          embedded ? 'pr-2 pl-2' : 'px-3',
        ]"
        aria-label="Về trang chủ"
      >
        <img
          src="/images/congnghe/brand/vas-white.png"
          alt="VAschools"
          :class="wordmarkClass"
          width="280"
          height="96"
          decoding="async"
        >
      </Link>
      <button
        v-if="!hideToggle"
        type="button"
        class="absolute right-2 top-2 z-20 grid h-9 w-9 min-h-[2.25rem] min-w-[2.25rem] place-items-center rounded-lg bg-white/10 text-white shadow-sm ring-1 ring-white/20 transition-colors hover:bg-white/20 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/50 active:scale-[0.98]"
        title="Thu gọn thanh bên"
        aria-label="Thu gọn thanh bên"
        @click.stop="emit('collapse')"
      >
        <AppIcon
          name="collapse-left"
          :size="18"
        />
      </button>
    </template>
  </div>
</template>
