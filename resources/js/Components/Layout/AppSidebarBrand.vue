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
    class="relative flex shrink-0 items-center border-b border-white/[0.08]"
    :class="rail ? 'h-14 justify-center px-0' : 'justify-center px-5 py-3.5'"
  >
    <template v-if="rail">
      <button
        type="button"
        class="group grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-white transition-colors hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/50"
        :title="appName"
        aria-label="Mở rộng thanh bên"
        @click="emit('expand')"
      >
        <span class="font-display text-sm font-bold uppercase tracking-tight">{{ appShortName }}</span>
      </button>
    </template>
    <template v-else>
      <Link
        href="/dashboard"
        class="flex items-center justify-center py-0.5"
      >
        <img
          src="/images/logo-2.png"
          alt="VAschools"
          class="h-12 w-auto object-contain"
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
