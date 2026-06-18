<script setup>
defineProps({
    rail: { type: Boolean, default: false },
    userInitials: { type: String, default: 'ND' },
    userAvatarSrc: { type: String, default: null },
    userDisplayName: { type: String, default: '' },
    roleLabel: { type: String, default: '' },
    showTip: { type: Function, default: null },
    hideTip: { type: Function, default: null },
});

const emit = defineEmits(['expand']);
</script>

<template>
  <div
    v-if="rail"
    class="flex flex-col items-center border-t border-white/[0.07] py-3"
  >
    <button
      type="button"
      class="relative rounded-full transition-transform hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
      aria-label="Mở rộng thanh bên"
      @click="emit('expand')"
      @mouseenter="showTip && showTip($event, userDisplayName, roleLabel)"
      @mouseleave="hideTip && hideTip()"
    >
      <img
        v-if="userAvatarSrc"
        :src="userAvatarSrc"
        :alt="userDisplayName"
        class="h-9 w-9 rounded-full object-cover ring-1 ring-white/15"
      >
      <div
        v-else
        class="grid h-9 w-9 select-none place-items-center rounded-full bg-white/10 text-[12px] font-bold leading-none text-white ring-1 ring-white/15"
      >
        {{ userInitials }}
      </div>
      <span
        class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-brand"
        aria-hidden="true"
      />
    </button>
  </div>
</template>
