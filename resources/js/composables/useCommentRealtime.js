import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    acquireSharedSocket,
    isSocketConnected,
    joinCommentRoom,
    registerHandlers,
    releaseSharedSocket,
    roomNameForComment,
    unregisterHandlers,
} from '@/composables/commentRealtimeHub';

/**
 * Socket.IO cho luồng bình luận (blocker, task, …).
 * Một kết nối dùng chung — nhiều thread / chuyển task không ngắt team khác.
 *
 * @param {import('vue').Ref<string>|import('vue').ComputedRef<string>} commentableType
 * @param {import('vue').Ref<number|string>|import('vue').ComputedRef<number|string>} commentableId
 * @param {{ onCreated?: (comment: object) => void, onUpdated?: (comment: object) => void, onDeleted?: (commentId: number) => void }} handlers
 */
export function useCommentRealtime(commentableType, commentableId, handlers = {}) {
    const page = usePage();
    const connected = ref(false);
    const subscribed = ref(false);
    let activeRoom = null;
    let pollId = null;

    const enabled = () => page.props.realtime?.enabled && page.props.realtime?.url;
    let socketHeld = false;

    const syncConnected = () => {
        connected.value = enabled() && isSocketConnected();
    };

    async function subscribe() {
        subscribed.value = false;
        syncConnected();
        if (!enabled()) {
            return;
        }
        const type = commentableType.value;
        const id = Number(commentableId.value);
        if (!type || !id) {
            return;
        }

        const url = page.props.realtime.url || window.location.origin;

        try {
            if (!socketHeld) {
                acquireSharedSocket(url, {
                    websocket: page.props.realtime?.websocket === true,
                });
                socketHeld = true;
            }
            const room = await joinCommentRoom({ url, type, id });
            if (activeRoom && activeRoom !== room) {
                unregisterHandlers(activeRoom, handlers);
            }
            activeRoom = room;
            registerHandlers(room, handlers);
            subscribed.value = true;
            syncConnected();
        } catch {
            subscribed.value = false;
            syncConnected();
        }
    }

    function leave() {
        if (activeRoom) {
            unregisterHandlers(activeRoom, handlers);
            activeRoom = null;
        }
        subscribed.value = false;
    }

    onMounted(() => {
        subscribe();
        pollId = window.setInterval(syncConnected, 2000);
    });

    watch([commentableType, commentableId], async () => {
        leave();
        await subscribe();
    });

    onBeforeUnmount(() => {
        if (pollId) {
            clearInterval(pollId);
            pollId = null;
        }
        leave();
        if (socketHeld) {
            releaseSharedSocket();
            socketHeld = false;
        }
        connected.value = false;
    });

    return { connected, subscribed };
}

export { roomNameForComment };
