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
const friendlyRole = computed(() => props.roleLabel || 'Người dùng');

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
    <!-- Nút mở menu -->
    <button
      type="button"
      class="flex items-center gap-2 rounded-xl border border-transparent px-2 py-1.5 transition-colors
             hover:border-slate-200 hover:bg-slate-50
             focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30"
      :aria-expanded="menuOpen"
      aria-haspopup="menu"
      aria-label="Mở menu tài khoản"
      @click="toggleMenu"
    >
      <img
        v-if="avatarSrc"
        :src="avatarSrc"
        :alt="displayName"
        class="h-8 w-8 shrink-0 rounded-full object-cover ring-1 ring-slate-200"
      >
      <div
        v-else
        class="flex h-8 w-8 shrink-0 select-none items-center justify-center rounded-full bg-brand text-[13px] font-bold tracking-tight text-white"
      >
        {{ initials }}
      </div>

      <div class="hidden text-left leading-snug sm:block">
        <p class="max-w-[140px] truncate text-sm font-semibold text-slate-800">
          {{ displayName }}
        </p>
        <p class="mt-0.5 text-xs text-slate-500">
          {{ friendlyRole }}
        </p>
      </div>

      <AppIcon
        name="chevron-down"
        :size="14"
        class="hidden text-slate-400 transition-transform duration-200 sm:block"
        :class="menuOpen ? 'rotate-180' : ''"
      />
    </button>

    <!-- Bảng menu -->
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
        class="absolute right-0 z-40 mt-2 w-[300px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg shadow-slate-900/10"
        role="menu"
        aria-label="Menu tài khoản"
      >
        <!-- Thông tin người dùng -->
        <div class="border-b border-slate-100 bg-slate-50 px-4 py-4">
          <div class="flex items-start gap-3">
            <img
              v-if="avatarSrc"
              :src="avatarSrc"
              :alt="displayName"
              class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-white"
            >
            <div
              v-else
              class="flex h-12 w-12 shrink-0 select-none items-center justify-center rounded-full bg-brand text-base font-bold text-white ring-2 ring-white"
            >
              {{ initials }}
            </div>

            <div class="min-w-0 flex-1 pt-0.5">
              <p class="truncate text-base font-semibold leading-snug text-slate-900">
                {{ displayName }}
              </p>
              <p
                v-if="email"
                class="mt-0.5 truncate text-sm leading-snug text-slate-500"
              >
                {{ email }}
              </p>
              <p
                v-else
                class="mt-0.5 text-sm leading-snug text-slate-400"
              >
                Chưa cập nhật email
              </p>
              <p class="mt-2 inline-flex rounded-md bg-white px-2 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200">
                Vai trò: {{ friendlyRole }}
              </p>
            </div>
          </div>
        </div>

        <!-- Các mục chọn -->
        <div class="p-2">
          <p class="px-2 pb-1.5 pt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            Tài khoản của bạn
          </p>

          <Link
            href="/profile"
            role="menuitem"
            class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 text-left transition-colors
                   hover:bg-slate-50
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/20"
            @click="closeMenu"
          >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand/10">
              <AppIcon
                name="account"
                :size="18"
                class="text-brand"
              />
            </div>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-slate-800">Xem hồ sơ</span>
              <span class="mt-0.5 block text-xs text-slate-500">Thông tin cá nhân và liên hệ</span>
            </span>
            <AppIcon
              name="chevron-right"
              :size="16"
              class="shrink-0 text-slate-300"
            />
          </Link>

          <button
            type="button"
            role="menuitem"
            class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 text-left transition-colors
                   hover:bg-slate-50
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/20"
            @click="emit('open-notifications'); closeMenu()"
          >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50">
              <AppIcon
                name="notifications"
                :size="18"
                class="text-sky-600"
              />
            </div>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-slate-800">Xem thông báo</span>
              <span class="mt-0.5 block text-xs text-slate-500">Tin nhắn và cập nhật mới</span>
            </span>
            <AppIcon
              name="chevron-right"
              :size="16"
              class="shrink-0 text-slate-300"
            />
          </button>
        </div>

        <!-- Đăng xuất -->
        <div class="border-t border-slate-100 p-2">
          <button
            type="button"
            role="menuitem"
            class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2.5 text-left transition-colors
                   hover:bg-rose-50
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-200"
            @click="logout"
          >
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50">
              <AppIcon
                name="logout"
                :size="18"
                class="text-rose-600"
              />
            </div>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-semibold text-rose-700">Đăng xuất</span>
              <span class="mt-0.5 block text-xs text-rose-500/80">Thoát khỏi hệ thống</span>
            </span>
          </button>
        </div>

        <div class="border-t border-slate-100 bg-slate-50 px-4 py-2 text-center">
          <p class="text-xs text-slate-400">
            VAschools Workspace
          </p>
        </div>
      </div>
    </transition>
  </div>
</template>
