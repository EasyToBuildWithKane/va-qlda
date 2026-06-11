<script setup>
import { computed, nextTick, onMounted, provide, reactive, ref, watch } from 'vue';
import { useOverflowScrollHints } from '@/composables/useOverflowScrollHints';
import { Link, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import UserMenu from '@/modules/project/components/UserMenu.vue';
import NotificationBell from '@/Components/Notifications/NotificationBell.vue';
import QuickBlockerReportBell from '@/Components/Blocker/QuickBlockerReportBell.vue';
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

// App identity (admin-editable via /settings, shared as `app` prop).
const appInfo = computed(() => page.props.app ?? {});
const appShortName = computed(() => appInfo.value.short_name || 'VA');
const appName = computed(() => appInfo.value.name || 'VAschools QLDA');
const appVersion = computed(() => appInfo.value.version || '1.0');

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

// --- Rail tooltip ---
// A single styled tooltip rendered via <Teleport to="body"> so it escapes the
// nav's overflow clipping. Positioned (fixed) at the hovered element's vertical
// centre, just right of the 64 px rail.
const tip = reactive({ show: false, label: '', sub: '', tone: '', top: 0 });
let tipTimer = null;
const showTip = (e, label, sub = '', tone = '') => {
    clearTimeout(tipTimer);
    const r = e.currentTarget.getBoundingClientRect();
    Object.assign(tip, { show: true, label, sub, tone, top: r.top + r.height / 2 });
};
const hideTip = () => {
    tipTimer = setTimeout(() => { tip.show = false; }, 60);
};
const tipSubClass = computed(() => ({
    amber: 'text-amber-300',
    sky: 'text-sky-300',
    accent: 'text-accent',
}[tip.tone] || 'text-slate-300'));

// Collapsed-rail status cue: a small dot for dev/maintenance items
// (planned items keep their amber clock badge).
const showRailStatus = (item) => !!item.status && item.status !== 'live' && item.status !== 'planned';
const railTone = (item) => {
    if (isPlanned(item)) return 'amber';
    if (item.status === 'maintenance') return 'sky';
    if (item.status === 'dev') return 'accent';
    return '';
};

// --- Per-group collapse (stable key, not display heading) ---
const COLLAPSE_KEY = 'va-qlda.sidebar.collapsed';
const groupKey = (group) => group.key ?? group.heading;

const collapsed = reactive(new Set());
const syncCollapsedFromStorage = () => {
    const raw = localStorage.getItem(COLLAPSE_KEY);
    collapsed.clear();
    if (raw) {
        JSON.parse(raw).forEach((k) => collapsed.add(k));
        return;
    }
    nav.value.filter((g) => g.defaultCollapsed).forEach((g) => collapsed.add(groupKey(g)));
};
watch(nav, syncCollapsedFromStorage, { immediate: true });
watch(collapsed, () => localStorage.setItem(COLLAPSE_KEY, JSON.stringify([...collapsed])), { deep: true });

const groupContainsActive = (group) => group.items.some((item) => isActive(item.href));

const isOpen = (group) => {
    if (groupContainsActive(group)) return true;
    return !collapsed.has(groupKey(group));
};

const toggleGroup = (group) => {
    const key = groupKey(group);
    if (collapsed.has(key)) {
        collapsed.delete(key);
        return;
    }
    if (!groupContainsActive(group)) collapsed.add(key);
};

watch(activeHref, () => {
    for (const group of nav.value) {
        if (groupContainsActive(group)) collapsed.delete(groupKey(group));
    }
});
const isUpcomingGroup = (group) => group.variant === 'upcoming';

// --- Module status (đang phát triển / bảo trì …) ---
// Source of truth is App\Support\Navigation; here we only map it to styling.
const STATUS = {
    live: { label: 'Đang hoạt động', dot: 'bg-emerald-400', pill: 'bg-emerald-400/15 text-emerald-200' },
    dev: { label: 'Đang phát triển', dot: 'bg-accent', pill: 'bg-accent/15 text-accent-soft' },
    maintenance: { label: 'Đang bảo trì', dot: 'bg-sky-400', pill: 'bg-sky-400/15 text-sky-200' },
    planned: {
        label: 'Sắp ra mắt',
        dot: 'bg-amber-300',
        pill: 'border border-amber-300/35 bg-amber-400/20 text-amber-50 shadow-sm shadow-amber-900/20',
    },
};
const statusOf = (item) => STATUS[item.status] ?? STATUS.live;
const isPlanned = (item) => item.status === 'planned' || item.href === '#';
// Badge on live groups only; upcoming section uses group header + clock hint.
const showBadge = (item, group) => item.status && item.status !== 'live' && !isUpcomingGroup(group);

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
const userAvatarSrc = computed(() => user.value?.employee?.avatar_path || null);
const userDisplayName = computed(() => user.value?.display_name || user.value?.name || 'Người dùng');

const { scrollEl: sidebarNavRef, edges: sidebarScrollEdges, onScroll: onSidebarNavScroll } =
    useOverflowScrollHints([rail, nav, collapsed]);

function scrollActiveNavItemIntoView() {
    nextTick(() => {
        const root = sidebarNavRef.value;
        if (!root) return;
        const active = root.querySelector('.sidebar-nav-item--active');
        active?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        onSidebarNavScroll();
    });
}

watch(activeHref, scrollActiveNavItemIntoView);
watch(rail, () => nextTick(scrollActiveNavItemIntoView));
onMounted(scrollActiveNavItemIntoView);

</script>

<template>
  <div class="flex h-screen min-h-0 overflow-hidden bg-slate-50">
    <!-- ══════════════════════════════════════════
             SIDEBAR
             Level 0 — Brand header
             Level 1 — Section/Group headings  (ALL CAPS, tiny, muted, collapsible)
             Level 2 — Navigation items        (normal case, 15 px, icon + label)
             ══════════════════════════════════════════ -->
    <aside
      class="flex h-full min-h-0 shrink-0 flex-col bg-brand text-brand-100 transition-all duration-200"
      :class="rail ? 'w-16' : 'w-72'"
    >
      <!-- ── Level 0: Brand + toggle ── -->
      <div
        class="relative flex items-center justify-center border-b border-white/[0.08]"
        :class="rail ? 'h-16 px-0' : 'px-5 py-4'"
      >
        <template v-if="rail">
          <div
            class="h-9 w-9 shrink-0 rounded-btn bg-white/10 text-white grid place-items-center font-display font-bold text-sm uppercase tracking-tight"
            :title="appName"
          >
            {{ appShortName }}
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

      <!-- Vùng nav cuộn + gợi ý fade khi còn mục phía trên/dưới -->
      <div class="relative flex min-h-0 flex-1 flex-col">
        <div
          v-show="sidebarScrollEdges.top"
          class="pointer-events-none absolute inset-x-0 top-0 z-10 h-7 bg-gradient-to-b from-brand via-brand/90 to-transparent"
          aria-hidden="true"
        />
        <div
          v-show="sidebarScrollEdges.bottom"
          class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-7 bg-gradient-to-t from-brand via-brand/90 to-transparent"
          aria-hidden="true"
        />

        <!-- ═══ Rail nav: grouped activity-bar ═══
             Group marker (section identity) + activity-bar items with a
             left accent bar for the active route and a styled hover tooltip. -->
        <nav
          v-if="rail"
          ref="sidebarNavRef"
          class="sidebar-nav-scroll min-h-0 flex-1 overflow-y-auto py-3 flex flex-col items-center gap-0.5"
          aria-label="Điều hướng chính"
          @scroll="onSidebarNavScroll"
        >
          <template
            v-for="(group, gi) in nav"
            :key="groupKey(group)"
          >
            <!-- Section divider -->
            <div
              v-if="gi > 0"
              class="my-1.5 h-px w-7"
              :class="isUpcomingGroup(group) ? 'bg-amber-300/30' : 'bg-white/[0.1]'"
              aria-hidden="true"
            />
            <!-- Group marker — hover shows the section heading -->
            <div
              class="mb-0.5 grid h-6 w-6 cursor-default place-items-center rounded-md"
              :class="isUpcomingGroup(group) ? 'text-amber-200/70' : 'text-brand-100/35'"
              @mouseenter="showTip($event, group.heading)"
              @mouseleave="hideTip"
            >
              <AppIcon
                :name="group.icon"
                :size="13"
              />
            </div>
            <!-- Items -->
            <component
              :is="isPlanned(item) ? 'div' : Link"
              v-for="item in group.items"
              :key="item.label"
              :href="isPlanned(item) ? undefined : item.href"
              class="relative grid h-11 w-11 place-items-center rounded-xl transition-colors"
              :class="[
                isActive(item.href)
                  ? 'sidebar-nav-item--active bg-white/[0.12] text-white'
                  : 'text-brand-100/60 hover:bg-white/[0.07] hover:text-white',
                isPlanned(item) && 'opacity-70 cursor-not-allowed hover:bg-amber-400/10 hover:text-amber-100/80',
              ]"
              @mouseenter="showTip($event, item.label, isPlanned(item) ? 'Sắp ra mắt' : (showRailStatus(item) ? statusOf(item).label : ''), railTone(item))"
              @mouseleave="hideTip"
            >
              <!-- Active accent bar at the rail's left edge -->
              <span
                v-if="isActive(item.href)"
                class="absolute left-0 -ml-3 h-5 w-1 rounded-r-full bg-accent"
                aria-hidden="true"
              />
              <AppIcon
                :name="item.icon"
                :size="19"
              />
              <!-- Planned badge -->
              <span
                v-if="isPlanned(item)"
                class="absolute -right-0.5 -top-0.5 grid h-3.5 w-3.5 place-items-center rounded-full bg-amber-400/90 ring-[1.5px] ring-brand"
              >
                <AppIcon
                  name="clock"
                  :size="8"
                  class="text-brand"
                />
              </span>
              <!-- Dev / maintenance status dot -->
              <span
                v-else-if="showRailStatus(item)"
                class="absolute right-0.5 top-0.5 h-2 w-2 rounded-full ring-2 ring-brand"
                :class="statusOf(item).dot"
              />
            </component>
          </template>
        </nav>

        <!-- ═══ Expanded nav ═══ -->
        <nav
          v-else
          ref="sidebarNavRef"
          class="sidebar-nav-scroll min-h-0 flex-1 overflow-y-auto px-3 py-4 pr-2"
          aria-label="Điều hướng chính"
          @scroll="onSidebarNavScroll"
        >
          <div
            v-for="(group, gi) in nav"
            :key="groupKey(group)"
            :class="[
              gi > 0 ? 'mt-1.5 pt-1.5 border-t border-white/[0.07]' : '',
              isUpcomingGroup(group) && 'mt-3 pt-3 border-t border-amber-300/25',
            ]"
          >
            <button
              type="button"
              class="group/head flex w-full min-h-8 items-center gap-2 rounded-lg px-2 py-1.5 transition-all duration-150 select-none"
              :class="isUpcomingGroup(group)
                ? 'text-[10px] font-bold uppercase tracking-[0.14em] text-amber-100/90 bg-amber-400/12 border border-amber-300/25 hover:bg-amber-400/18 hover:text-amber-50'
                : 'text-[10px] font-bold uppercase tracking-[0.14em] text-brand-100/50 hover:text-brand-100/75 hover:bg-white/[0.04]'"
              :aria-expanded="isOpen(group)"
              @click="toggleGroup(group)"
            >
              <AppIcon
                :name="group.icon"
                :size="13"
                class="shrink-0 transition-opacity"
                :class="isUpcomingGroup(group) ? 'text-amber-200/90' : 'opacity-55 group-hover/head:opacity-80'"
              />
              <span class="min-w-0 flex-1 truncate text-left">{{ group.heading }}</span>
              <span
                v-if="isUpcomingGroup(group)"
                class="shrink-0 rounded-full border border-amber-300/40 bg-amber-400/25 px-1.5 py-0.5 text-[9px] font-bold tabular-nums text-amber-50 leading-none"
              >
                {{ group.items.length }}
              </span>
              <span
                class="grid h-5 w-5 shrink-0 place-items-center rounded-md text-brand-100/40 transition-colors group-hover/head:text-brand-100/65"
                :class="isUpcomingGroup(group) && 'text-amber-100/50 group-hover/head:text-amber-50/80'"
                aria-hidden="true"
              >
                <AppIcon
                  name="chevron-down"
                  :size="12"
                  class="transition-transform duration-200 ease-out"
                  :class="isOpen(group) ? 'rotate-0' : '-rotate-90'"
                />
              </span>
            </button>

            <!--
                        ── Level 2: Navigation items list ──
                        15 px · normal case · icon + label
                        Active: brighter background + white text
                    -->
            <div
              class="grid transition-[grid-template-rows] duration-200 ease-out"
              :class="isOpen(group) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
            >
              <div class="min-h-0 overflow-hidden">
                <ul
                  class="mt-0.5 mb-0.5 space-y-px"
                  :class="isUpcomingGroup(group) && 'rounded-lg border border-amber-300/15 bg-amber-950/20 p-1'"
                >
                  <li
                    v-for="item in group.items"
                    :key="item.label"
                  >
                    <component
                      :is="isPlanned(item) ? 'div' : Link"
                      :href="isPlanned(item) ? undefined : item.href"
                      :title="isPlanned(item) ? 'Sắp ra mắt — chưa khả dụng' : undefined"
                      class="group/item flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[14px] leading-snug transition-all duration-150"
                      :class="[
                        isActive(item.href)
                          ? 'sidebar-nav-item--active bg-white/[0.12] text-white font-semibold shadow-sm'
                          : isUpcomingGroup(group)
                            ? 'text-amber-100/65 hover:bg-amber-400/10 hover:text-amber-50'
                            : 'text-brand-100/80 hover:bg-white/[0.06] hover:text-white',
                        isPlanned(item) && 'cursor-not-allowed',
                      ]"
                    >
                      <AppIcon
                        :name="item.icon"
                        :size="17"
                        class="shrink-0 transition-opacity"
                        :class="isActive(item.href) ? 'opacity-100' : 'opacity-55 group-hover/item:opacity-85'"
                      />

                      <span class="truncate flex-1">{{ item.label }}</span>

                      <span
                        v-if="showBadge(item, group)"
                        class="ml-auto shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold leading-none"
                        :class="statusOf(item).pill"
                      >
                        <span
                          class="h-1.5 w-1.5 rounded-full"
                          :class="statusOf(item).dot"
                        />
                        {{ statusOf(item).label }}
                      </span>

                      <AppIcon
                        v-else-if="isPlanned(item)"
                        name="clock"
                        :size="14"
                        class="ml-auto shrink-0 text-amber-300/70"
                      />

                      <span
                        v-else-if="isActive(item.href)"
                        class="ml-auto h-[6px] w-[6px] rounded-full bg-accent shrink-0"
                      />
                    </component>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </nav>
      </div>

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
          <img
            v-if="userAvatarSrc"
            :src="userAvatarSrc"
            :alt="userDisplayName"
            class="h-7 w-7 shrink-0 rounded-full object-cover ring-1 ring-white/15"
          >
          <div
            v-else
            class="h-7 w-7 shrink-0 rounded-full bg-white/10 ring-1 ring-white/15 flex items-center justify-center text-[11px] font-bold text-white leading-none select-none"
          >
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
          <span class="text-[10px] text-brand-100/30 tracking-wide truncate">{{ appName }} · v{{ appVersion }}</span>
          <span class="text-[10px] text-brand-100/30 shrink-0 pl-1.5">© {{ new Date().getFullYear() }}</span>
        </div>
      </div>

      <!-- ── Rail footer: user avatar (click to expand) ── -->
      <div
        v-if="rail"
        class="border-t border-white/[0.07] py-3 flex flex-col items-center"
      >
        <button
          type="button"
          class="relative rounded-full transition-transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-accent/40"
          aria-label="Mở rộng thanh bên"
          @click="rail = false"
          @mouseenter="showTip($event, userDisplayName, roleLabel)"
          @mouseleave="hideTip"
        >
          <img
            v-if="userAvatarSrc"
            :src="userAvatarSrc"
            :alt="userDisplayName"
            class="h-9 w-9 rounded-full object-cover ring-1 ring-white/15"
          >
          <div
            v-else
            class="h-9 w-9 rounded-full bg-white/10 ring-1 ring-white/15 grid place-items-center text-[12px] font-bold text-white leading-none select-none"
          >
            {{ userInitials }}
          </div>
          <span
            class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-brand"
            aria-hidden="true"
          />
        </button>
      </div>
    </aside>

    <!-- Main column -->
    <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
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
              <QuickBlockerReportBell />
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

      <main :class="flush ? 'flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden p-0' : 'min-h-0 flex-1 overflow-y-auto p-6'">
        <slot />
      </main>
    </div>

    <!-- App-wide dialog (replaces native alert/confirm/prompt) -->
    <AppDialog />

    <!-- Global toast notifications -->
    <ToastContainer />

    <!-- Notification center (right drawer) -->
    <NotificationCenterDrawer />

    <!-- Rail hover tooltip — Teleported so it escapes the sidebar overflow -->
    <Teleport to="body">
      <transition
        enter-active-class="transition duration-100 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-75 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="tip.show && rail"
          :style="{ top: tip.top + 'px' }"
          class="pointer-events-none fixed left-[68px] z-[60] -translate-y-1/2 rounded-lg bg-slate-900/95 px-2.5 py-1.5 shadow-elevation-2 ring-1 ring-white/10"
          role="tooltip"
        >
          <span class="block whitespace-nowrap text-[12.5px] font-medium leading-tight text-white">{{ tip.label }}</span>
          <span
            v-if="tip.sub"
            class="block whitespace-nowrap text-[10.5px] leading-tight"
            :class="tipSubClass"
          >{{ tip.sub }}</span>
        </div>
      </transition>
    </Teleport>
  </div>
</template>
