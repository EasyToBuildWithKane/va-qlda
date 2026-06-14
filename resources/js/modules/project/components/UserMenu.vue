<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    roleLabel: { type: String, default: '' },
});

const emit = defineEmits(['open-notifications']);

const menuOpen = ref(false);
const menuRef = ref(null);

const initials = computed(() => {
    const name = (props.user?.display_name || props.user?.name || 'Người dùng').trim();
    const parts = name.split(/\s+/).filter(Boolean);
    if (!parts.length) return 'ND';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

const displayName = computed(() => props.user?.display_name || props.user?.name || 'Người dùng');
const email = computed(() => props.user?.email || '');
const avatarSrc = computed(() => props.user?.employee?.avatar_path || null);

const closeMenu = () => { menuOpen.value = false; };
const toggleMenu = () => { menuOpen.value = !menuOpen.value; };
const logout = () => { closeMenu(); router.post('/logout'); };

const onPointerDown = (e) => { if (menuRef.value && !menuRef.value.contains(e.target)) closeMenu(); };
const onEscape = (e) => { if (e.key === 'Escape') closeMenu(); };

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
    <!-- ── Trigger button ── -->
    <button
      type="button"
      class="flex items-center gap-2 rounded-xl px-2 py-1.5 transition-all
                   border border-transparent hover:border-slate-200 hover:bg-slate-50
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30"
      :aria-expanded="menuOpen"
      aria-haspopup="menu"
      aria-label="Mở menu tài khoản"
      @click="toggleMenu"
    >
      <!-- Avatar -->
      <img
        v-if="avatarSrc"
        :src="avatarSrc"
        :alt="displayName"
        class="h-8 w-8 shrink-0 rounded-full object-cover ring-1 ring-slate-200/80"
      >
      <div
        v-else
        class="h-8 w-8 shrink-0 rounded-full bg-brand flex items-center justify-center font-bold text-white text-[13px] tracking-tight select-none"
      >
        {{ initials }}
      </div>
      <!-- Name + role (hidden on small screens) -->
      <div class="hidden sm:block text-left leading-tight">
        <p class="max-w-[140px] truncate text-[13px] font-semibold text-slate-800">
          {{ displayName }}
        </p>
        <p class="text-[11px] text-slate-400 mt-0.5">
          {{ roleLabel || 'Người dùng' }}
        </p>
      </div>
      <AppIcon
        name="chevron-down"
        :size="13"
        class="hidden sm:block text-slate-300 transition-transform duration-200"
        :class="menuOpen ? 'rotate-180' : ''"
      />
    </button>

    <!-- ── Dropdown panel ── -->
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
        class="absolute right-0 z-40 mt-2 w-[280px] overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-xl shadow-slate-900/10"
        role="menu"
        aria-label="Menu tài khoản"
      >
        <!-- ── Account header: brand gradient ── -->
        <div class="bg-gradient-to-br from-brand via-brand to-brand/80 px-4 pt-4 pb-3.5">
          <div class="flex items-center gap-3">
            <!-- Large avatar -->
            <img
              v-if="avatarSrc"
              :src="avatarSrc"
              :alt="displayName"
              class="h-11 w-11 shrink-0 rounded-full object-cover ring-2 ring-white/30"
            >
            <div
              v-else
              class="h-11 w-11 shrink-0 rounded-full bg-white/20 ring-2 ring-white/30 flex items-center justify-center font-bold text-white text-[15px] select-none"
            >
              {{ initials }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-[14px] font-semibold text-white leading-tight truncate">
                {{ displayName }}
              </p>
              <p
                v-if="email"
                class="text-[11.5px] text-white/60 leading-tight mt-0.5 truncate"
              >
                {{ email }}
              </p>
              <p
                v-else
                class="text-[11.5px] text-white/40 leading-tight mt-0.5"
              >
                Chưa có email
              </p>
            </div>
          </div>
          <!-- Role + status badges -->
          <div class="mt-3 flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center rounded-full bg-white/20 px-2.5 py-1 text-[11px] font-semibold text-white leading-none">
              {{ roleLabel || 'Người dùng' }}
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11px] text-white/80 leading-none">
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 shrink-0" />
              Đang hoạt động
            </span>
          </div>
        </div>

        <!-- ── Menu items ── -->
        <div class="p-1.5 space-y-0.5">
          <!-- My profile -->
          <Link
            href="/profile"
            role="menuitem"
            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/20"
            @click="closeMenu"
          >
            <div class="h-7 w-7 shrink-0 rounded-lg bg-brand/10 flex items-center justify-center">
              <AppIcon
                name="account"
                :size="14"
                class="text-brand"
              />
            </div>
            <span class="flex-1 text-left">Hồ sơ của tôi</span>
          </Link>

          <!-- Notifications -->
          <button
            type="button"
            role="menuitem"
            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/20"
            @click="emit('open-notifications'); closeMenu()"
          >
            <div class="h-7 w-7 shrink-0 rounded-lg bg-brand/10 flex items-center justify-center">
              <AppIcon
                name="notifications"
                :size="14"
                class="text-brand"
              />
            </div>
            <span class="flex-1 text-left">Thông báo</span>
          </button>

          <!-- Settings (coming soon) -->
          <div class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] text-slate-400 cursor-not-allowed select-none">
            <div class="h-7 w-7 shrink-0 rounded-lg bg-slate-100 flex items-center justify-center">
              <AppIcon
                name="system-config"
                :size="14"
                class="text-slate-400"
              />
            </div>
            <span class="flex-1">Cài đặt tài khoản</span>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-400">Sắp ra mắt</span>
          </div>
        </div>

        <!-- ── Divider + Logout ── -->
        <div class="border-t border-slate-100 p-1.5">
          <button
            type="button"
            role="menuitem"
            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] font-medium text-rose-600 transition-colors hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-200"
            @click="logout"
          >
            <div class="h-7 w-7 shrink-0 rounded-lg bg-rose-50 flex items-center justify-center">
              <AppIcon
                name="logout"
                :size="14"
                class="text-rose-500"
              />
            </div>
            <span>Đăng xuất</span>
          </button>
        </div>

        <!-- ── Footer: app version ── -->
        <div class="border-t border-slate-100 bg-slate-50/70 px-4 py-2.5 flex items-center justify-between">
          <span class="text-[10.5px] text-slate-400">VAschools QLDA · v1.0</span>
          <span class="text-[10.5px] text-slate-400">Phòng Công nghệ</span>
        </div>
      </div>
    </transition>
  </div>
</template>
