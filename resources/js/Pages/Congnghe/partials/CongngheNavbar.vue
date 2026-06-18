<script setup>
import {
    computed, onBeforeUnmount, onMounted, ref, watch,
} from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import CongngheUserMenu from './CongngheUserMenu.vue';
import RippleSurface from './RippleSurface.vue';
import { congngheBrand } from './congngheBrand.js';
import CongngheBrandImage from './CongngheBrandImage.vue';
import { useCongngheFocusTrap } from './useCongngheFocusTrap.js';

const page = usePage();

const props = defineProps({
    content: { type: Object, default: () => ({}) },
});

const onLanding = computed(() => {
    const path = page.url.split('?')[0].replace(/\/$/, '') || '/';
    return path === '/congnghe';
});

function sectionHref(id) {
    return onLanding.value ? `#${id}` : `/congnghe#${id}`;
}

const proposalHref = '/congnghe/de-xuat';

const authUser = computed(() => page.props.auth?.user ?? null);
const userName = computed(
    () => authUser.value?.employee?.full_name
        ?? authUser.value?.display_name
        ?? authUser.value?.username
        ?? 'Thành viên',
);
const userEmail = computed(() => authUser.value?.email ?? '');
const userAvatar = computed(() => authUser.value?.employee?.avatar_path ?? null);
const userRole = computed(() => {
    const role = authUser.value?.role;
    if (role === 'super_admin') return 'Super Admin';
    if (role === 'admin') return 'Quản trị';
    if (role === 'lead') return 'Trưởng nhóm';
    if (role === 'member') return 'Thành viên';
    if (role === 'viewer') return 'Xem';
    return role ?? '';
});

const brandName = computed(() => props.content?.brand_name ?? 'Phòng Công Nghệ');
const brandTagline = computed(() => props.content?.brand_tagline ?? '');
const links = computed(() => (props.content?.links ?? []).map((l) => ({
    id: String(l.anchor ?? '').replace(/^#/, ''),
    label: l.label,
})));

const scrolled = ref(false);
const open = ref(false);
const activeId = ref('');
const mobileNav = ref(null);
const visibleSections = new Map();

useCongngheFocusTrap(mobileNav, open);

function onScroll() {
    scrolled.value = window.scrollY > 12;
}

function closeMenu() {
    open.value = false;
}

function onKeydown(e) {
    if (e.key === 'Escape') {
        closeMenu();
    }
}

let spy = null;

onMounted(() => {
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('keydown', onKeydown);

    if (typeof IntersectionObserver !== 'undefined') {
        spy = new IntersectionObserver(
            (entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) {
                        visibleSections.set(e.target.id, e.intersectionRatio);
                    } else {
                        visibleSections.delete(e.target.id);
                    }
                });

                let bestId = '';
                let bestRatio = 0;
                visibleSections.forEach((ratio, id) => {
                    if (ratio >= bestRatio) {
                        bestRatio = ratio;
                        bestId = id;
                    }
                });
                if (bestId) {
                    activeId.value = bestId;
                }
            },
            { rootMargin: '-42% 0px -48% 0px', threshold: [0, 0.15, 0.35, 0.55, 0.75] },
        );
        links.value.forEach((l) => {
            const el = document.getElementById(l.id);
            if (el) {
                spy.observe(el);
            }
        });
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('keydown', onKeydown);
    spy?.disconnect();
    document.body.style.overflow = '';
});

watch(open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

const headerShellClass = computed(() => (
    scrolled.value
        ? 'border-white/10 bg-[#06070f]/95 shadow-[0_10px_40px_-16px_rgba(154,0,54,0.45)]'
        : 'border-cyan-500/15 bg-[#070912]/78 shadow-[0_8px_32px_-12px_rgba(0,0,0,0.55)]'
));

const navPadClass = computed(() => (scrolled.value ? 'py-2.5 sm:py-3' : 'py-3.5 sm:py-4 md:py-[1.125rem]'));
</script>

<template>
  <header
    class="fixed inset-x-0 top-0 z-50 border-b backdrop-blur-xl transition-[background-color,box-shadow,border-color,padding] duration-300"
    :class="headerShellClass"
  >
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-cyan-400/50 to-transparent"
      aria-hidden="true"
    />
    <div
      class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-cyan-500/10 via-brand/35 to-violet-500/10"
      aria-hidden="true"
    />
    <div
      class="pointer-events-none absolute inset-0 animate-cn-pulse-grid opacity-[0.28]"
      aria-hidden="true"
      style="background-image: linear-gradient(rgba(56,189,248,0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(56,189,248,0.05) 1px, transparent 1px); background-size: 28px 28px;"
    />
    <div
      class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand/[0.07] via-transparent to-transparent"
      aria-hidden="true"
    />

    <nav
      class="relative mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:grid lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] lg:items-center lg:gap-4"
      :class="navPadClass"
    >
      <!-- Brand -->
      <a
        href="#top"
        class="group flex min-w-0 items-center gap-2.5 lg:justify-self-start"
      >
        <CongngheBrandImage
          isolated-cutout
          :src="congngheBrand.badgeCircle"
          alt="Vietnam America Schools"
          class="rounded-xl ring-1 ring-cyan-500/25 transition duration-300 group-hover:ring-brand/50"
          :class="scrolled ? 'h-9 w-9 sm:h-10 sm:w-10' : 'h-10 w-10 sm:h-11 sm:w-11 md:h-12 md:w-12'"
          width="40"
          height="40"
        />
        <span class="min-w-0 flex flex-col leading-tight">
          <span class="truncate font-display text-sm font-bold text-white sm:text-base md:text-[1.05rem]">
            {{ brandName }}
          </span>
          <span
            v-if="brandTagline"
            class="hidden font-mono text-[10px] tracking-[0.14em] text-cyan-200/45 sm:block md:text-[11px]"
          >
            {{ brandTagline }}
          </span>
        </span>
      </a>

      <!-- Desktop nav — căn giữa -->
      <div
        class="hidden lg:flex lg:justify-center lg:justify-self-center"
        role="navigation"
        aria-label="Mục trang"
      >
        <div
          class="flex items-center gap-0.5 rounded-full border border-white/10 bg-[#0a0c16]/90 p-1.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.06)]"
        >
          <a
            v-for="link in links"
            :key="link.id"
            :href="sectionHref(link.id)"
            class="relative rounded-full px-3.5 py-2 text-[13px] font-medium transition-colors will-change-transform focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/50 md:px-4 md:text-sm"
            :class="activeId === link.id ? 'text-white' : 'text-white/55 hover:text-white/90'"
            :aria-current="activeId === link.id ? 'true' : undefined"
          >
            <span
              v-if="activeId === link.id"
              class="absolute inset-0 rounded-full bg-gradient-to-r from-brand via-[#8f0030] to-[#4a1030] shadow-[0_4px_16px_-4px_rgba(154,0,54,0.65)] ring-1 ring-cyan-400/25"
            />
            <span class="relative">{{ link.label }}</span>
          </a>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex shrink-0 items-center justify-end gap-2 lg:justify-self-end">
        <Link
          :href="proposalHref"
          class="relative hidden h-10 items-center gap-1.5 overflow-hidden rounded-full border border-brand/45 bg-brand/20 px-4 text-xs font-semibold text-white shadow-[0_4px_20px_-6px_rgba(154,0,54,0.65)] transition hover:bg-brand/35 sm:flex sm:text-sm"
        >
          <span class="relative z-10">Đề xuất PM</span>
          <RippleSurface color="rgba(255,77,141,0.35)" />
        </Link>
        <div
          v-if="authUser"
          class="hidden md:block"
        >
          <CongngheUserMenu
            :name="userName"
            :email="userEmail"
            :avatar-src="userAvatar"
            :role-label="userRole"
          />
        </div>

        <button
          type="button"
          class="relative grid h-10 w-10 place-items-center rounded-xl border border-white/10 bg-white/[0.06] text-white/85 transition hover:bg-white/10 lg:hidden"
          :aria-expanded="open"
          aria-controls="congnghe-mobile-nav"
          aria-label="Mở menu điều hướng"
          @click="open = !open"
        >
          <svg
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            aria-hidden="true"
          >
            <path :d="open ? 'M6 6l12 12M6 18L18 6' : 'M4 7h16M4 12h16M4 17h16'" />
          </svg>
        </button>
      </div>
    </nav>

    <!-- Mobile menu -->
    <Teleport to="body">
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
      >
        <button
          v-if="open"
          type="button"
          class="fixed inset-0 z-[45] bg-[#020308]/75 backdrop-blur-sm lg:hidden"
          aria-label="Đóng menu"
          @click="closeMenu"
        />
      </transition>
    </Teleport>

    <transition
      enter-active-class="transition duration-250 ease-out"
      enter-from-class="-translate-y-3 opacity-0"
      leave-active-class="transition duration-200 ease-in"
      leave-to-class="-translate-y-2 opacity-0"
    >
      <div
        v-if="open"
        id="congnghe-mobile-nav"
        ref="mobileNav"
        class="relative z-[46] border-t border-white/10 bg-[#080a12]/98 px-4 py-4 backdrop-blur-xl lg:hidden"
      >
        <div
          v-if="authUser"
          class="mb-4 space-y-2"
        >
          <CongngheUserMenu
            :name="userName"
            :email="userEmail"
            :avatar-src="userAvatar"
            :role-label="userRole"
            compact
            class="w-full"
          />
        </div>

        <div class="grid grid-cols-2 gap-2">
          <Link
            :href="route('congnghe.proposal.mine')"
            class="col-span-2 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-2.5 text-center text-sm font-medium text-white/85"
            @click="closeMenu"
          >
            Đề xuất đã gửi
          </Link>
          <Link
            :href="proposalHref"
            class="col-span-2 rounded-xl border border-brand/45 bg-brand/25 px-3 py-2.5 text-center text-sm font-semibold text-white"
            @click="closeMenu"
          >
            Đề xuất giải pháp phần mềm
          </Link>
          <a
            v-for="link in links"
            :key="link.id"
            :href="sectionHref(link.id)"
            class="rounded-xl border px-3 py-2.5 text-sm font-medium transition"
            :class="activeId === link.id
              ? 'border-brand/50 bg-brand/20 text-white'
              : 'border-white/10 bg-white/[0.03] text-white/75 hover:border-white/20 hover:bg-white/[0.06] hover:text-white'"
            @click="closeMenu"
          >{{ link.label }}</a>
        </div>
      </div>
    </transition>
  </header>
</template>
