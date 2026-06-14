<script setup>
defineProps({
    rail: { type: Boolean, default: false },
    legend: { type: Array, default: () => [] },
    user: { type: Object, default: null },
    roleLabel: { type: String, default: '' },
    userInitials: { type: String, default: 'ND' },
    userAvatarSrc: { type: String, default: null },
    userDisplayName: { type: String, default: '' },
    appName: { type: String, default: '' },
    appVersion: { type: String, default: '1.0' },
    showTip: { type: Function, default: null },
    hideTip: { type: Function, default: null },
});

const emit = defineEmits(['expand']);
</script>

<template>
  <div
    v-if="!rail && legend.length"
    class="border-t border-white/[0.08] px-4 py-3"
  >
    <p class="mb-2.5 text-[9.5px] font-black uppercase tracking-[0.18em] text-brand-100/30">
      Trạng thái module
    </p>
    <ul class="grid grid-cols-2 gap-x-3 gap-y-2">
      <li
        v-for="s in legend"
        :key="s.key"
        class="flex items-center gap-1.5 text-[11.5px] text-brand-100/55"
      >
        <span
          class="h-1.5 w-1.5 shrink-0 rounded-full"
          :class="s.dot"
        />
        <span class="truncate">{{ s.label }}</span>
      </li>
    </ul>
  </div>

  <div
    v-if="!rail"
    class="space-y-3 border-t border-white/[0.07] px-4 py-3.5"
  >
    <div class="flex items-center gap-2.5">
      <img
        v-if="userAvatarSrc"
        :src="userAvatarSrc"
        :alt="userDisplayName"
        class="h-8 w-8 shrink-0 rounded-full object-cover ring-1 ring-white/15"
      >
      <div
        v-else
        class="flex h-8 w-8 shrink-0 select-none items-center justify-center rounded-full bg-white/10 text-[11px] font-bold leading-none text-white ring-1 ring-white/15"
      >
        {{ userInitials }}
      </div>
      <div class="min-w-0 flex-1">
        <p class="truncate text-[12px] font-medium leading-tight text-brand-100/80">
          {{ user?.display_name || user?.name || 'Người dùng' }}
        </p>
        <p class="mt-0.5 truncate text-[10.5px] leading-tight text-brand-100/40">
          {{ roleLabel }}
        </p>
      </div>
      <span
        class="h-2 w-2 shrink-0 rounded-full bg-emerald-400 ring-[1.5px] ring-brand"
        title="Đang hoạt động"
      />
    </div>
    <div class="flex items-center justify-between border-t border-white/[0.06] pt-2.5">
      <span class="truncate text-[10px] tracking-wide text-brand-100/30">{{ appName }} · v{{ appVersion }}</span>
      <span class="shrink-0 pl-1.5 text-[10px] text-brand-100/30">© {{ new Date().getFullYear() }}</span>
    </div>
  </div>

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
