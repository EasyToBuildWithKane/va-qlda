import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { io } from 'socket.io-client';

const CONNECT_TIMEOUT_MS = 15000;

/**
 * Socket.IO cho luồng bình luận (blocker, task, …).
 * Laravel publish Redis → Node `realtime/server.mjs` → browser.
 *
 * @param {import('vue').Ref<string>|import('vue').ComputedRef<string>} commentableType
 * @param {import('vue').Ref<number|string>|import('vue').ComputedRef<number|string>} commentableId
 * @param {{ onCreated?: (comment: object) => void, onUpdated?: (comment: object) => void, onDeleted?: (commentId: number) => void }} handlers
 */
export function useCommentRealtime(commentableType, commentableId, handlers = {}) {
    const page = usePage();
    const connected = ref(false);
    const subscribed = ref(false);
    let socket = null;
    let activeRoom = null;
    let latestToken = null;

    const enabled = () => page.props.realtime?.enabled && page.props.realtime?.url;

    function attachCommentListener(sock) {
        sock.off('comment');
        sock.on('comment', (payload) => {
            const curType = commentableType.value;
            const curId = Number(commentableId.value);
            if (!payload || payload.commentable_type !== curType || Number(payload.commentable_id) !== curId) {
                return;
            }
            if (payload.event === 'comment.created' && payload.data?.comment) {
                handlers.onCreated?.(payload.data.comment);
            }
            if (payload.event === 'comment.updated' && payload.data?.comment) {
                handlers.onUpdated?.(payload.data.comment);
            }
            if (payload.event === 'comment.deleted' && payload.data?.comment_id) {
                handlers.onDeleted?.(Number(payload.data.comment_id));
            }
        });
    }

    function ensureSocket() {
        if (socket) {
            return socket;
        }

        const socketUrl = page.props.realtime.url || window.location.origin;
        socket = io(socketUrl, {
            path: '/socket.io',
            transports: ['websocket', 'polling'],
            withCredentials: true,
            reconnection: true,
            reconnectionAttempts: 8,
        });

        attachCommentListener(socket);

        socket.on('connect', () => {
            connected.value = true;
            if (latestToken) {
                emitSubscribe(latestToken).catch(() => {
                    subscribed.value = false;
                });
            }
        });

        socket.on('disconnect', () => {
            connected.value = false;
            subscribed.value = false;
            activeRoom = null;
        });

        return socket;
    }

    function emitSubscribe(token) {
        return new Promise((resolve, reject) => {
            if (!socket) {
                reject(new Error('no_socket'));
                return;
            }
            socket.emit('subscribe:comments', { token }, (res) => {
                if (res?.ok) {
                    activeRoom = res.room;
                    subscribed.value = true;
                    resolve();
                } else {
                    subscribed.value = false;
                    reject(new Error(res?.error || 'subscribe_failed'));
                }
            });
        });
    }

    function waitForConnect(sock) {
        if (sock.connected) {
            return Promise.resolve();
        }
        return new Promise((resolve, reject) => {
            const timer = setTimeout(() => {
                reject(new Error('connect_timeout'));
            }, CONNECT_TIMEOUT_MS);
            sock.once('connect', () => {
                clearTimeout(timer);
                resolve();
            });
            sock.once('connect_error', (err) => {
                clearTimeout(timer);
                reject(err);
            });
        });
    }

    async function subscribe() {
        subscribed.value = false;
        if (!enabled()) {
            return;
        }
        const type = commentableType.value;
        const id = Number(commentableId.value);
        if (!type || !id) {
            return;
        }

        try {
            const { data } = await axios.get('/realtime/thread-token', {
                params: { type, id },
            });
            if (!data?.token) {
                return;
            }

            latestToken = data.token;
            const sock = ensureSocket();
            await waitForConnect(sock);
            await emitSubscribe(latestToken);
        } catch {
            subscribed.value = false;
        }
    }

    function leaveRoom() {
        if (socket && activeRoom) {
            socket.emit('unsubscribe:comments', { room: activeRoom });
            activeRoom = null;
        }
        subscribed.value = false;
        latestToken = null;
    }

    function teardownSocket() {
        leaveRoom();
        if (socket) {
            socket.disconnect();
            socket = null;
        }
        connected.value = false;
    }

    onMounted(() => {
        subscribe();
    });

    watch([commentableType, commentableId], async () => {
        leaveRoom();
        await subscribe();
    });

    onBeforeUnmount(() => {
        teardownSocket();
    });

    return { connected, subscribed };
}
