<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    rail: { type: Boolean, default: false },
    appShortName: { type: String, default: 'VA' },
    appName: { type: String, default: '' },
    hideToggle: { type: Boolean, default: false },
});

const emit = defineEmits(['collapse', 'expand']);
</script>

<template>
  <div
    class="relative flex shrink-0 items-center border-b border-white/[0.10]"
    :class="rail ? 'h-[4.25rem] justify-center px-0' : 'h-[5.75rem] justify-center px-4 py-3'"
  >
    <template v-if="rail">
      <button
        type="button"
        class="group grid h-11 w-11 place-items-center rounded-xl bg-white/10 p-1.5 text-white transition-colors hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/50"
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
        class="flex min-w-0 flex-1 items-center justify-center pr-8"
        aria-label="VAschools — Bảng điều khiển"
      >
        <img
          src="/images/congnghe/brand/vas-white.png"
          alt="VAschools"
          class="h-[4.25rem] w-auto max-w-[14.5rem] object-contain drop-shadow-[0_2px_10px_rgba(0,0,0,0.28)]"
        >
      </Link>
      <button
        v-if="!hideToggle"
        type="button"
        class="absolute right-2 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-brand-100/50 transition-colors hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
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
