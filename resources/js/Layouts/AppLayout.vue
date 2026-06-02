<script setup>
import { computed, provide, reactive, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import UserMenu from '@/modules/project/components/UserMenu.vue';
import NotificationBell from '@/Components/Notifications/NotificationBell.vue';
import NotificationCenterDrawer from '@/Components/Notifications/NotificationCenterDrawer.vue';
import AppDialog from '@/Components/Ui/AppDialog.vue';
import ToastContainer from '@/Components/Ui/ToastContainer.vue';
import { useToast } from '@/shared/composables/useToast';
import { useNotifications } from '@/composables/useNotifications';

const notificationCenter = useNotifications();
provide('notifications', notificationCenter);

const { flush } = defineProps({ flush: Boolean });
const page = usePage();
const user = computed(() => page.props.auth?.user);
const nav = computed(() => page.props.nav ?? []);
const flash = computed(() => page.props.flash ?? {});

const toast = useToast();
watch(flash, (f) => {
    if (f.success) toast.success(f.success);
    if (f.error)   toast.error(f.error);
}, { immediate: true, deep: true });

// Highlight only the most specific matching item. A plain startsWith() would
// keep "/daily-reports" (Lịch sử) active while on "/daily-reports/today", so we
// pick the longest href that prefixes the current URL.
const allHrefs = computed(() =>
    nav.value.flatMap((g) => g.items.map((i) => i.href)).filter((h) => h !== '#'),
);
const activeHref = computed(() => {
    const url = page.url.split('?')[0];
    let best = '';
    for (const h of allHrefs.value) {
        if ((url === h || url.startsWith(h + '/')) && h.length > best.length) best = h;
    }
    return best;
});
const isActive = (href) => href !== '#' && href === activeHref.value;

const roleLabels = {
    admin: 'Quản trị viên',
    lead: 'Trưởng nhóm',
    member: 'Thành viên',
    viewer: 'Người xem',
};
const roleLabel = computed(() => roleLabels[user.value?.role] ?? user.value?.role ?? '');

// --- Whole-sidebar collapse (rail mode) ---
const RAIL_KEY = 'va-qlda.sidebar.rail';
const rail = ref(localStorage.getItem(RAIL_KEY) === '1');
watch(rail, (v) => localStorage.setItem(RAIL_KEY, v ? '1' : '0'));

// --- Per-group collapse ---
const COLLAPSE_KEY = 'va-qlda.sidebar.collapsed';
const collapsed = reactive(new Set(JSON.parse(localStorage.getItem(COLLAPSE_KEY) || '[]')));
watch(collapsed, () => localStorage.setItem(COLLAPSE_KEY, JSON.stringify([...collapsed])), { deep: true });

const isOpen = (group) => !collapsed.has(group.heading);
const toggleGroup = (group) => {
    collapsed.has(group.heading) ? collapsed.delete(group.heading) : collapsed.add(group.heading);
};

// --- Module status (đang phát triển / bảo trì …) ---
// Source of truth is App\Support\Navigation; here we only map it to styling.
const STATUS = {
    live: { label: 'Đang hoạt động', dot: 'bg-emerald-400', pill: 'bg-emerald-400/15 text-emerald-200' },
    dev: { label: 'Đang phát triển', dot: 'bg-accent', pill: 'bg-accent/15 text-accent-soft' },
    maintenance: { label: 'Đang bảo trì', dot: 'bg-sky-400', pill: 'bg-sky-400/15 text-sky-200' },
    planned: { label: 'Sắp ra mắt', dot: 'bg-white/30', pill: 'bg-white/10 text-brand-100/60' },
};
const statusOf = (item) => STATUS[item.status] ?? STATUS.live;
// "live" is the silent default — only the exceptions get a visible badge.
const showBadge = (item) => item.status && item.status !== 'live';
const isPlanned = (item) => item.status === 'planned' || item.href === '#';

// Legend at the foot of the sidebar — only show states actually in use.
const legend = computed(() => {
    const used = new Set(nav.value.flatMap((g) => g.items.map((i) => i.status || 'live')));
    return Object.entries(STATUS)
        .filter(([key]) => used.has(key))
        .map(([key, v]) => ({ key, ...v }));
});

// Topbar: formatted current date shown next to UserMenu.
const currentDate = computed(() =>
    new Date().toLocaleDateString('vi-VN', {
        weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric',
    }),
);

// Sidebar footer: avatar initials for the logged-in user.
const userInitials = computed(() => {
    const name = (user.value?.display_name || user.value?.name || 'ND').trim();
    const parts = name.split(/\s+/).filter(Boolean);
    if (!parts.length) return 'ND';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

</script>

<template>
  <div class="min-h-screen flex bg-slate-50">
    <!-- ══════════════════════════════════════════
             SIDEBAR
             Level 0 — Brand header
             Level 1 — Section/Group headings  (ALL CAPS, tiny, muted, collapsible)
             Level 2 — Navigation items        (normal case, 15 px, icon + label)
             ══════════════════════════════════════════ -->
    <aside
      class="shrink-0 bg-brand text-brand-100 flex flex-col transition-all duration-200"
      :class="rail ? 'w-16' : 'w-72'"
    >
      <!-- ── Level 0: Brand + toggle ── -->
      <div
        class="relative flex items-center justify-center border-b border-white/[0.08]"
        :class="rail ? 'h-16 px-0' : 'px-5 py-4'"
      >
        <template v-if="rail">
          <div class="h-9 w-9 shrink-0 rounded-btn bg-white/10 text-white grid place-items-center font-display font-bold text-sm">
            VA
          </div>
        </template>
        <template v-else>
          <Link
            href="/dashboard"
            class="flex items-center justify-center py-1"
          >
            <img
              src="/images/logo-2.png"
              alt="VAschools"
              class="h-14 w-auto object-contain"
            >
          </Link>
          <button
            class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-lg text-brand-100/50 hover:bg-white/10 hover:text-white transition-colors"
            title="Thu gọn thanh bên"
            @click="rail = true"
          >
            <AppIcon
              name="collapse-left"
              :size="17"
            />
          </button>
        </template>
      </div>

      <!-- Rail: expand button -->
      <button
        v-if="rail"
        class="mx-auto mt-3 grid h-9 w-9 place-items-center rounded-lg text-brand-100/60 hover:bg-white/10 hover:text-white transition-colors"
        title="Mở rộng thanh bên"
        @click="rail = false"
      >
        <AppIcon
          name="expand-left"
          :size="17"
        />
      </button>

      <!-- ═══ Rail nav: flat icon strip ═══ -->
      <nav
        v-if="rail"
        class="flex-1 overflow-y-auto py-3 flex flex-col items-center gap-0.5"
      >
        <template
          v-for="(group, gi) in nav"
          :key="group.heading"
        >
          <div
            v-if="gi > 0"
            class="w-8 my-2 border-t border-white/[0.1]"
          />
          <component
            :is="isPlanned(item) ? 'div' : Link"
            v-for="item in group.items"
            :key="item.label"
            :href="isPlanned(item) ? undefined : item.href"
            :title="`${item.label}${showBadge(item) ? ' · ' + statusOf(item).label : ''}`"
            class="relative grid h-10 w-10 place-items-center rounded-lg transition-colors"
            :class="[
              isActive(item.href)
                ? 'bg-white/[0.12] text-white'
                : 'text-brand-100/60 hover:bg-white/[0.07] hover:text-white',
              isPlanned(item) && 'opacity-40 cursor-not-allowed hover:bg-transparent hover:text-brand-100/60',
            ]"
          >
            <AppIcon
              :name="item.icon"
              :size="19"
            />
            <span
              v-if="showBadge(item)"
              class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full ring-[1.5px] ring-brand"
              :class="statusOf(item).dot"
            />
          </component>
        </template>
      </nav>

      <!-- ═══ Expanded nav ═══ -->
      <nav
        v-else
        class="flex-1 overflow-y-auto px-3 py-4"
      >
        <div
          v-for="(group, gi) in nav"
          :key="group.heading"
          :class="gi > 0 ? 'mt-2 pt-2 border-t border-white/[0.07]' : ''"
        >
          <!--
                        ── Level 1: Section heading ──
                        ALL CAPS · 10.5 px · font-black · wide tracking · muted (45% opacity)
                        Clearly a LABEL/DIVIDER, not a clickable link
                    -->
          <button
            type="button"
            class="group/head w-full flex items-center gap-2.5 px-2 py-2 rounded-lg
                               text-[10.5px] font-black uppercase tracking-[0.18em]
                               text-brand-100/45 hover:text-brand-100/70 hover:bg-white/[0.04]
                               transition-all duration-150 select-none"
            @click="toggleGroup(group)"
          >
            <AppIcon
              :name="group.icon"
              :size="13"
              class="shrink-0 opacity-55 group-hover/head:opacity-80 transition-opacity"
            />
            <span class="flex-1 text-left">{{ group.heading }}</span>
            <AppIcon
              name="chevron"
              :size="11"
              class="shrink-0 opacity-40 transition-transform duration-200"
              :class="isOpen(group) ? 'rotate-90' : 'rotate-0'"
            />
          </button>

          <!--
                        ── Level 2: Navigation items list ──
                        15 px · normal case · icon + label
                        Active: brighter background + white text
                    -->
          <ul
            v-show="isOpen(group)"
            class="mt-1 mb-0.5 space-y-0.5"
          >
            <li
              v-for="item in group.items"
              :key="item.label"
            >
              <component
                :is="isPlanned(item) ? 'div' : Link"
                :href="isPlanned(item) ? undefined : item.href"
                :title="showBadge(item) ? statusOf(item).label : undefined"
                class="group/item flex items-center gap-3 rounded-xl px-3.5 py-[0.6875rem] text-[15px] leading-snug transition-all duration-150"
                :class="[
                  isActive(item.href)
                    ? 'bg-white/[0.12] text-white font-semibold shadow-sm'
                    : 'text-brand-100/75 hover:bg-white/[0.06] hover:text-white',
                  isPlanned(item) && 'cursor-not-allowed text-brand-100/35 hover:bg-transparent hover:text-brand-100/35',
                ]"
              >
                <!-- Icon: brighter when active, dims otherwise -->
                <AppIcon
                  :name="item.icon"
                  :size="18"
                  class="shrink-0 transition-opacity"
                  :class="isActive(item.href) ? 'opacity-100' : 'opacity-60 group-hover/item:opacity-85'"
                />

                <span class="truncate flex-1">{{ item.label }}</span>

                <!-- Status badge (non-live modules) -->
                <span
                  v-if="showBadge(item)"
                  class="ml-auto shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold leading-none"
                  :class="statusOf(item).pill"
                >
                  <span
                    class="h-1.5 w-1.5 rounded-full"
                    :class="statusOf(item).dot"
                  />
                  {{ statusOf(item).label }}
                </span>

                <!-- Active dot (live, no badge) -->
                <span
                  v-else-if="isActive(item.href)"
                  class="ml-auto h-[7px] w-[7px] rounded-full bg-accent shrink-0"
                />
              </component>
            </li>
          </ul>
        </div>
      </nav>

      <!-- Status legend -->
      <div
        v-if="!rail && legend.length"
        class="px-4 py-3 border-t border-white/[0.08]"
      >
        <p class="text-[9.5px] font-black uppercase tracking-[0.18em] text-brand-100/30 mb-2.5">
          Trạng thái module
        </p>
        <ul class="grid grid-cols-2 gap-x-3 gap-y-2">
          <li
            v-for="s in legend"
            :key="s.key"
            class="flex items-center gap-1.5 text-[11.5px] text-brand-100/55"
          >
            <span
              class="h-1.5 w-1.5 rounded-full shrink-0"
              :class="s.dot"
            />
            <span class="truncate">{{ s.label }}</span>
          </li>
        </ul>
      </div>

      <!-- ── Sidebar footer: user quick-info + app info ── -->
      <div
        v-if="!rail"
        class="px-4 py-3.5 border-t border-white/[0.07] space-y-3"
      >
        <!-- Logged-in user summary -->
        <div class="flex items-center gap-2.5">
          <div class="h-7 w-7 shrink-0 rounded-full bg-white/10 ring-1 ring-white/15 flex items-center justify-center text-[11px] font-bold text-white leading-none select-none">
            {{ userInitials }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-[12px] font-medium text-brand-100/75 truncate leading-tight">
              {{ user?.display_name || user?.name || 'Người dùng' }}
            </p>
            <p class="text-[10.5px] text-brand-100/40 leading-tight truncate mt-0.5">
              {{ roleLabel }}
            </p>
          </div>
          <!-- Online indicator -->
          <span
            class="h-2 w-2 rounded-full bg-emerald-400 shrink-0 ring-[1.5px] ring-brand"
            title="Đang hoạt động"
          />
        </div>

        <!-- App version + copyright -->
        <div class="flex items-center justify-between border-t border-white/[0.06] pt-2.5">
          <span class="text-[10px] text-brand-100/30 tracking-wide">VAschools QLDA · v1.0</span>
          <span class="text-[10px] text-brand-100/30">© {{ new Date().getFullYear() }}</span>
        </div>
      </div>
    </aside>

    <!-- Main column -->
    <div class="flex-1 flex flex-col min-w-0">
      <!--
                Topbar — h-14 (56 px)
                Left  : #header slot  — standardised via PageHeader component
                Right : current-date chip · divider · UserMenu
            -->
      <slot name="topbar">
        <header class="h-14 shrink-0 border-b border-slate-200/80 bg-white px-5 shadow-[0_1px_4px_0_rgb(0,0,0,0.04)]">
          <div class="flex h-full items-center gap-4">
            <!-- Left: page identity (each page fills this via #header) -->
            <div class="flex-1 min-w-0">
              <slot name="header">
                <div class="flex items-center gap-2.5">
                  <div class="shrink-0 h-8 w-8 rounded-lg bg-brand/10 flex items-center justify-center">
                    <AppIcon
                      name="dashboard"
                      :size="15"
                      class="text-brand"
                    />
                  </div>
                  <div>
                    <h1 class="text-[15px] font-semibold text-slate-800 leading-none">
                      Bảng điều khiển
                    </h1>
                    <p class="text-[11.5px] text-slate-400 mt-0.5 leading-none">
                      Tổng quan hệ thống
                    </p>
                  </div>
                </div>
              </slot>
            </div>

            <!-- Right: date chip + separator + UserMenu -->
            <div class="flex items-center gap-2.5 shrink-0">
              <div class="hidden lg:flex items-center gap-1.5 rounded-lg bg-slate-50 border border-slate-100/80 px-3 py-1.5 text-[11.5px] text-slate-500 leading-none select-none">
                <AppIcon
                  name="daily"
                  :size="12"
                  class="text-slate-300 shrink-0"
                />
                <span>{{ currentDate }}</span>
              </div>
              <div class="h-5 w-px bg-slate-200 hidden sm:block" />
              <NotificationBell />
              <UserMenu
                :user="user"
                :role-label="roleLabel"
                @open-notifications="notificationCenter.openDrawer()"
              />
            </div>
          </div>
        </header>
      </slot>

      <main :class="flush ? 'flex-1 overflow-hidden flex flex-col min-h-0 p-4' : 'flex-1 p-6 overflow-y-auto'">
        <slot />
      </main>
    </div>

    <!-- App-wide dialog (replaces native alert/confirm/prompt) -->
    <AppDialog />

    <!-- Global toast notifications -->
    <ToastContainer />

    <!-- Notification center (right drawer) -->
    <NotificationCenterDrawer />
  </div>
</template>
