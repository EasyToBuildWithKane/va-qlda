import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const POLL_MS = 30_000;

/**
 * Global notification state: unread badge, drawer list, filters, polling.
 */
export function useNotifications() {
    const page = usePage();
    const unreadCount = ref(page.props.notifications?.unread_count ?? 0);
    const items = ref([]);
    const loading = ref(false);
    const loadingMore = ref(false);
    const hasMore = ref(false);
    const nextCursor = ref(null);
    const drawerOpen = ref(false);
    const settingsOpen = ref(false);
    const selectedIds = ref([]);

    const filters = ref({ tab: 'all' });

    let pollTimer = null;

    watch(
        () => page.props.notifications?.unread_count,
        (v) => {
            if (typeof v === 'number') unreadCount.value = v;
        },
    );

    async function fetchUnread() {
        try {
            const { data } = await axios.get(route('notifications.unread-count'));
            unreadCount.value = data.count ?? 0;
        } catch {
            /* silent */
        }
    }

    async function fetchList({ append = false } = {}) {
        if (append) loadingMore.value = true;
        else loading.value = true;

        try {
            const params = {
                tab: filters.value.tab,
                per_page: 25,
                cursor: append ? nextCursor.value : undefined,
            };

            const { data } = await axios.get(route('notifications.index'), { params });
            const rows = data.data ?? [];

            if (append) items.value = [...items.value, ...rows];
            else items.value = rows;

            hasMore.value = !!data.meta?.has_more;
            nextCursor.value = data.meta?.next_cursor ?? null;
        } finally {
            loading.value = false;
            loadingMore.value = false;
        }
    }

    function openDrawer(tab = 'all') {
        filters.value.tab = tab;
        drawerOpen.value = true;
        fetchList();
    }

    function closeDrawer() {
        drawerOpen.value = false;
        selectedIds.value = [];
    }

    async function markRead(notification) {
        if (notification.is_read) return;
        try {
            const { data } = await axios.post(route('notifications.read', notification.id));
            const idx = items.value.findIndex((n) => n.id === notification.id);
            if (idx >= 0) items.value[idx] = data.notification;
            unreadCount.value = data.unread_count ?? unreadCount.value;
        } catch {
            /* */
        }
    }

    async function markAllRead() {
        try {
            await axios.post(route('notifications.read-all'));
            items.value = items.value.map((n) => ({ ...n, is_read: true, read_at: new Date().toISOString() }));
            unreadCount.value = 0;
        } catch {
            /* */
        }
    }

    async function acknowledge(notification) {
        try {
            const { data } = await axios.post(route('notifications.acknowledge', notification.id));
            const idx = items.value.findIndex((n) => n.id === notification.id);
            if (idx >= 0) items.value[idx] = data.notification;
            await markRead(notification);
        } catch {
            /* */
        }
    }

    async function bulkRead() {
        if (!selectedIds.value.length) return;
        try {
            const { data } = await axios.post(route('notifications.bulk'), {
                ids: selectedIds.value,
                action: 'read',
            });
            unreadCount.value = data.unread_count ?? 0;
            await fetchList();
            selectedIds.value = [];
        } catch {
            /* */
        }
    }

    function navigate(notification) {
        markRead(notification);
        if (notification.action_url) {
            router.visit(notification.action_url, { preserveScroll: true });
            closeDrawer();
        }
    }

    function applyFilters() {
        nextCursor.value = null;
        fetchList();
    }

    function loadMore() {
        if (!hasMore.value || loadingMore.value) return;
        fetchList({ append: true });
    }

    const badgeLabel = computed(() => {
        if (unreadCount.value <= 0) return '';
        return unreadCount.value > 99 ? '99+' : String(unreadCount.value);
    });

    const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(fetchUnread, POLL_MS);
    }

    function stopPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = null;
    }

    onMounted(() => {
        startPolling();
    });

    onUnmounted(stopPolling);

    return {
        unreadCount,
        badgeLabel,
        items,
        loading,
        loadingMore,
        hasMore,
        drawerOpen,
        settingsOpen,
        filters,
        selectedIds,
        isAdmin,
        openDrawer,
        closeDrawer,
        fetchList,
        fetchUnread,
        markRead,
        markAllRead,
        acknowledge,
        bulkRead,
        navigate,
        applyFilters,
        loadMore,
    };
}
