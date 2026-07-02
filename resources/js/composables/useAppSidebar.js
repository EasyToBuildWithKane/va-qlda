import { computed, nextTick, onBeforeUnmount, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useOverflowScrollHints } from '@/composables/useOverflowScrollHints';

const RAIL_KEY = 'va-qlda.sidebar.rail';
const COLLAPSE_KEY = 'va-qlda.sidebar.collapsed';
const MOBILE_BREAKPOINT = 1024;

// Module-level: survives composable re-instantiation across Inertia navigations
// (AppLayout remounts on each navigation in template-based layout mode)
let _sidebarScrollTop = 0;

const ROLE_LABELS = {
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

export function useAppSidebar() {
    const page = usePage();
    const nav = computed(() => page.props.nav ?? []);
    const user = computed(() => page.props.auth?.user);

    const appInfo = computed(() => page.props.app ?? {});
    const appShortName = computed(() => appInfo.value.short_name || 'VA');
    const appName = computed(() => appInfo.value.name || 'VAschools QLDA');
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

    watch(nav, syncCollapsedFromStorage, { immediate: true });
    watch(collapsed, () => {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(COLLAPSE_KEY, JSON.stringify([...collapsed]));
        }
    }, { deep: true });

    const groupContainsActive = (group) => group.items.some((item) => isActive(item.href));

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

    function persistSidebarScroll() {
        const el = sidebarNavRef.value;
        if (el) _sidebarScrollTop = el.scrollTop;
    }

    // Save scroll before refs are cleared on unmount (backup if scroll handler missed).
    onBeforeUnmount(persistSidebarScroll);

    const { edges: sidebarScrollEdges, onScroll: onSidebarNavScroll } = useOverflowScrollHints(
        [rail, nav, collapsed],
        sidebarNavRef,
    );

    function onSidebarNavScrollWithPersist(e) {
        persistSidebarScroll();
        onSidebarNavScroll(e);
    }

    function isActiveNavItemInView(container, el) {
        if (!container || !el) return true;
        const c = container.getBoundingClientRect();
        const e = el.getBoundingClientRect();
        return e.top >= c.top - 1 && e.bottom <= c.bottom + 1;
    }

    function applySidebarScrollRestore(root) {
        if (!root) return;
        if (root.scrollTop !== _sidebarScrollTop) {
            root.scrollTop = _sidebarScrollTop;
        }
    }

    function restoreSidebarScroll() {
        nextTick(() => {
            applySidebarScrollRestore(sidebarNavRef.value);
            onSidebarNavScroll();
        });
    }

    function scrollActiveNavItemIntoView(behavior = 'instant') {
        nextTick(() => {
            const root = sidebarNavRef.value;
            if (!root) return;

            applySidebarScrollRestore(root);

            const active = root.querySelector('.sidebar-nav-item--active');
            if (active && !isActiveNavItemInView(root, active)) {
                active.scrollIntoView({ block: 'nearest', behavior });
                persistSidebarScroll();
            }
            onSidebarNavScroll();
        });
    }

    watch(
        () => page.url,
        () => {
            restoreSidebarScroll();
        },
    );

    watch(rail, (v) => {
        if (!v) closeFlyout();
        nextTick(() => scrollActiveNavItemIntoView('smooth'));
    });
    onMounted(restoreSidebarScroll);

    return {
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
        scrollActiveNavItemIntoView,
        sidebarScrollEdges,
        onSidebarNavScroll: onSidebarNavScrollWithPersist,
    };
}
