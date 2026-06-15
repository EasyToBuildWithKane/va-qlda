<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';

defineProps({
    name: { type: String, default: 'Thành viên' },
    email: { type: String, default: '' },
    avatarSrc: { type: String, default: null },
    roleLabel: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});

const menuOpen = ref(false);
const menuRef = ref(null);

const closeMenu = () => { menuOpen.value = false; };
const toggleMenu = () => { menuOpen.value = !menuOpen.value; };
const logout = () => { closeMenu(); router.post('/logout'); };

const onPointerDown = (e) => {
    if (menuRef.value && !menuRef.value.contains(e.target)) {
        closeMenu();
    }
};
const onEscape = (e) => {
    if (e.key === 'Escape') {
        closeMenu();
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onEscape);
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown);
    document.removeEventListener('keydown', onEscape);
});
</script>

<template>
  <div
    ref="menuRef"
    class="relative"
  >
    <button
      type="button"
      class="flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.05] py-1 pl-1 pr-2.5 text-left transition hover:border-white/20 hover:bg-white/[0.08] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/40"
      :class="compact ? 'max-w-[200px]' : 'max-w-[240px]'"
      :aria-expanded="menuOpen"
      aria-haspopup="menu"
      aria-label="Mở menu tài khoản"
      @click="toggleMenu"
    >
      <Avatar
        :name="name"
        :src="avatarSrc"
        :size="34"
      />
      <div
        v-if="!compact"
        class="min-w-0 leading-tight"
      >
        <p class="truncate text-xs font-semibold text-white">
          {{ name }}
        </p>
        <p
          v-if="roleLabel"
          class="truncate font-mono text-[10px] uppercase tracking-wide text-cyan-200/40"
        >
          {{ roleLabel }}
        </p>
      </div>
      <AppIcon
        name="chevron-down"
        :size="12"
        class="shrink-0 text-white/40 transition-transform duration-200"
        :class="menuOpen ? 'rotate-180' : ''"
      />
    </button>

    <transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="translate-y-1 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-1 opacity-0"
    >
      <div
        v-if="menuOpen"
        class="absolute right-0 z-[60] mt-2 w-[min(100vw-2rem,280px)] overflow-hidden rounded-2xl border border-white/10 bg-[#0c0e18]/98 shadow-2xl shadow-black/50 backdrop-blur-xl"
        role="menu"
        aria-label="Menu tài khoản"
      >
        <div class="border-b border-white/10 bg-gradient-to-br from-brand via-[#7a0028] to-[#1a0a14] px-4 pt-4 pb-3.5">
          <div class="flex items-center gap-3">
            <Avatar
              :name="name"
              :src="avatarSrc"
              :size="44"
            />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-white">
                {{ name }}
              </p>
              <p
                v-if="email"
                class="truncate text-[11px] text-white/55"
              >
                {{ email }}
              </p>
              <p
                v-else
                class="text-[11px] text-white/40"
              >
                Chưa có email
              </p>
            </div>
          </div>
          <div
            v-if="roleLabel"
            class="mt-3 flex flex-wrap gap-2"
          >
            <span class="inline-flex rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold text-white">
              {{ roleLabel }}
            </span>
          </div>
        </div>

        <div class="p-1.5 space-y-0.5">
          <Link
            :href="route('congnghe.proposal.mine')"
            role="menuitem"
            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] text-white/85 transition hover:bg-white/[0.06]"
            @click="closeMenu"
          >
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-cyan-500/15">
              <AppIcon
                name="rocket"
                :size="14"
                class="text-cyan-300"
              />
            </div>
            <span class="flex-1 text-left">Đề xuất đã gửi</span>
          </Link>

          <Link
            href="/profile"
            role="menuitem"
            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] text-white/85 transition hover:bg-white/[0.06]"
            @click="closeMenu"
          >
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/10">
              <AppIcon
                name="account"
                :size="14"
                class="text-white/70"
              />
            </div>
            <span class="flex-1 text-left">Thông tin tài khoản</span>
          </Link>
        </div>

        <div class="border-t border-white/10 p-1.5">
          <button
            type="button"
            role="menuitem"
            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] font-medium text-rose-300 transition hover:bg-rose-500/10"
            @click="logout"
          >
            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-rose-500/15">
              <AppIcon
                name="logout"
                :size="14"
                class="text-rose-400"
              />
            </div>
            <span>Đăng xuất</span>
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>
