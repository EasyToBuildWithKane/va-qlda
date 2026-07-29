import { computed, inject, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useOverflowScrollHints } from '@/composables/useOverflowScrollHints';

export const APP_SIDEBAR_KEY = Symbol('vaAppSidebar');

const RAIL_KEY = 'va-workspace.sidebar.rail';
const COLLAPSE_KEY = 'va-workspace.sidebar.collapsed';
const MOBILE_BREAKPOINT = 1024;

const ROLE_LABELS = {
    super_admin: 'Siêu quản trị',
    admin: 'Quản trị viên',
    lead: 'Quản lý',
    member: 'Thành viên',
    viewer: 'Người xem',
};

export const SIDEBAR_STATUS = {
    live: { label: 'Đang hoạt động', dot: 'bg-emerald-400', pill: 'bg-emerald-400/15 text-emerald-200' },
    dev: { label: 'Đang phát triển', dot: 'bg-accent', pill: 'bg-accent/15 text-accent-soft' },
    maintenance: { label: 'Đang bảo trì', dot: 'bg-sky-400', pill: 'bg-sky-400/15 text-sky-200' },
    planned: {
        label: 'Sắp ra mắt',
        dot: 'bg-amber-300',
        pill: 'border border-amber-300/35 bg-amber-400/20 text-amber-50 shadow-sm shadow-amber-900/20',
    },
};

/** Sidebar shell gắn trên AppChrome — một instance, không remount theo Inertia page. */
let sidebarShellInstance = null;

export function useAppSidebar() {
    if (sidebarShellInstance) {
        return sidebarShellInstance;
    }

    const page = usePage();
    const nav = computed(() => page.props?.nav ?? []);
    const user = computed(() => page.props?.auth?.user);

    const appInfo = computed(() => page.props?.app ?? {});
    const appShortName = computed(() => appInfo.value.short_name || 'VA');
    const appName = computed(() => appInfo.value.name || 'VAschools Workspace');
    const appVersion = computed(() => appInfo.value.version || '1.0');

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

    const roleLabel = computed(() => ROLE_LABELS[user.value?.role] ?? user.value?.role ?? '');

    const rail = ref(typeof localStorage !== 'undefined' && localStorage.getItem(RAIL_KEY) === '1');
    watch(rail, (v) => {
        if (typeof localStorage !== 'undefined') localStorage.setItem(RAIL_KEY, v ? '1' : '0');
    });

    const isMobile = ref(false);
    const mobileOpen = ref(false);

    const updateMobile = () => {
        isMobile.value = typeof window !== 'undefined' && window.innerWidth < MOBILE_BREAKPOINT;
        if (!isMobile.value) mobileOpen.value = false;
    };

    onMounted(() => {
        updateMobile();
        window.addEventListener('resize', updateMobile, { passive: true });
    });

    onUnmounted(() => {
        window.removeEventListener('resize', updateMobile);
    });

    watch(
        () => page.url,
        () => {
            mobileOpen.value = false;
            closeFlyout();
        },
    );

    const openMobile = () => {
        mobileOpen.value = true;
    };

    const closeMobile = () => {
        mobileOpen.value = false;
    };

    const toggleMobile = () => {
        mobileOpen.value = !mobileOpen.value;
    };

    const groupKey = (group) => group.key ?? group.heading;
    const collapsed = reactive(new Set());

    const syncCollapsedFromStorage = () => {
        collapsed.clear();
        const raw = typeof localStorage !== 'undefined' ? localStorage.getItem(COLLAPSE_KEY) : null;
        if (raw) {
            JSON.parse(raw).forEach((k) => collapsed.add(k));
            return;
        }
        nav.value.filter((g) => g.defaultCollapsed).forEach((g) => collapsed.add(groupKey(g)));
    };

    const groupContainsActive = (group) => group.items.some((item) => isActive(item.href));

    /** Luôn mở nhóm chứa route đang active (parity production). */
    const ensureActiveGroupsOpen = () => {
        nav.value.forEach((g) => {
            if (groupContainsActive(g)) {
                collapsed.delete(groupKey(g));
            }
        });
    };

    watch(
        nav,
        () => {
            syncCollapsedFromStorage();
            ensureActiveGroupsOpen();
        },
        { immediate: true },
    );
    watch(activeHref, ensureActiveGroupsOpen);
    watch(collapsed, () => {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(COLLAPSE_KEY, JSON.stringify([...collapsed]));
        }
    }, { deep: true });

    const isOpen = (group) => !collapsed.has(groupKey(group));

    const toggleGroup = (group) => {
        const key = groupKey(group);
        if (collapsed.has(key)) {
            collapsed.delete(key);
        } else {
            collapsed.add(key);
        }
    };

    const isUpcomingGroup = (group) => group.variant === 'upcoming';
    const statusOf = (item) => SIDEBAR_STATUS[item.status] ?? SIDEBAR_STATUS.live;
    const isPlanned = (item) => item.status === 'planned' || item.href === '#';
    const showBadge = (item, group) => item.status && item.status !== 'live' && !isUpcomingGroup(group);

    const showRailStatus = (item) => !!item.status && item.status !== 'live' && item.status !== 'planned';
    const railTone = (item) => {
        if (isPlanned(item)) return 'amber';
        if (item.status === 'maintenance') return 'sky';
        if (item.status === 'dev') return 'accent';
        return '';
    };

    const userInitials = computed(() => {
        const name = (user.value?.display_name || user.value?.name || 'ND').trim();
        const parts = name.split(/\s+/).filter(Boolean);
        if (!parts.length) return 'ND';
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    });

    const userAvatarSrc = computed(() => user.value?.employee?.avatar_path || null);
    const userDisplayName = computed(() => user.value?.display_name || user.value?.name || 'Người dùng');

    const tip = reactive({ show: false, label: '', sub: '', tone: '', top: 0, left: 72 });
    let tipTimer = null;

    const showTip = (e, label, sub = '', tone = '') => {
        clearTimeout(tipTimer);
        const r = e.currentTarget.getBoundingClientRect();
        Object.assign(tip, {
            show: true,
            label,
            sub,
            tone,
            top: r.top + r.height / 2,
            left: r.right + 10,
        });
    };

    const hideTip = () => {
        tipTimer = setTimeout(() => {
            tip.show = false;
        }, 80);
    };

    const cancelHideTip = () => {
        clearTimeout(tipTimer);
    };

    const flyout = reactive({
        open: false,
        group: null,
        top: 0,
        left: 72,
    });

    let flyoutTimer = null;

    const openFlyout = (group, el) => {
        clearTimeout(flyoutTimer);
        if (!group?.items?.length) return;
        const key = groupKey(group);
        if (flyout.open && flyout.group && groupKey(flyout.group) === key) {
            closeFlyout();
            return;
        }
        const r = el.getBoundingClientRect();
        flyout.group = group;
        flyout.top = Math.max(8, Math.min(r.top, window.innerHeight - 320));
        flyout.left = r.right + 8;
        flyout.open = true;
        tip.show = false;
    };

    const scheduleFlyout = (group, el, delay = 180) => {
        clearTimeout(flyoutTimer);
        flyoutTimer = setTimeout(() => openFlyout(group, el), delay);
    };

    const closeFlyout = () => {
        clearTimeout(flyoutTimer);
        flyout.open = false;
        flyout.group = null;
    };

    const onFlyoutPointerLeave = () => {
        flyoutTimer = setTimeout(closeFlyout, 120);
    };

    const cancelFlyoutClose = () => {
        clearTimeout(flyoutTimer);
    };

    const sidebarNavRef = ref(null);

    const { edges: sidebarScrollEdges, onScroll: onSidebarNavScroll } = useOverflowScrollHints(
        [rail, nav, collapsed],
        sidebarNavRef,
    );

    watch(rail, (v) => {
        if (!v) closeFlyout();
    });

    sidebarShellInstance = {
        nav,
        user,
        appShortName,
        appName,
        appVersion,
        activeHref,
        isActive,
        roleLabel,
        rail,
        isMobile,
        mobileOpen,
        openMobile,
        closeMobile,
        toggleMobile,
        groupKey,
        collapsed,
        isOpen,
        toggleGroup,
        groupContainsActive,
        isUpcomingGroup,
        statusOf,
        isPlanned,
        showBadge,
        showRailStatus,
        railTone,
        userInitials,
        userAvatarSrc,
        userDisplayName,
        tip,
        showTip,
        hideTip,
        cancelHideTip,
        flyout,
        openFlyout,
        scheduleFlyout,
        closeFlyout,
        onFlyoutPointerLeave,
        cancelFlyoutClose,
        sidebarNavRef,
        sidebarScrollEdges,
        onSidebarNavScroll,
    };

    return sidebarShellInstance;
}

/** Trang con (AppLayout): lấy sidebar từ AppChrome hoặc khởi tạo khi test/mount đơn lẻ. */
export function useAppSidebarContext() {
    const fromChrome = inject(APP_SIDEBAR_KEY, null);
    return fromChrome ?? useAppSidebar();
}
