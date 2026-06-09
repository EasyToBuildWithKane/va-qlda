import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { io } from 'socket.io-client';

/**
 * Đăng ký Socket.IO cho luồng bình luận (blocker, task, …).
 *
 * @param {import('vue').Ref<string>|import('vue').ComputedRef<string>} commentableType
 * @param {import('vue').Ref<number|string>|import('vue').ComputedRef<number|string>} commentableId
 * @param {{ onCreated?: (comment: object) => void, onDeleted?: (commentId: number) => void }} handlers
 */
export function useCommentRealtime(commentableType, commentableId, handlers = {}) {
    const page = usePage();
    const connected = ref(false);
    let socket = null;
    let activeRoom = null;

    const enabled = () => page.props.realtime?.enabled && page.props.realtime?.url;

    async function subscribe() {
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

            if (!socket) {
                const socketUrl = page.props.realtime.url || window.location.origin;
                socket = io(socketUrl, {
                    path: '/socket.io',
                    transports: ['websocket', 'polling'],
                    withCredentials: true,
                });
                socket.on('connect', () => {
                    connected.value = true;
                });
                socket.on('disconnect', () => {
                    connected.value = false;
                });
                socket.on('comment', (payload) => {
                    const curType = commentableType.value;
                    const curId = Number(commentableId.value);
                    if (!payload || payload.commentable_type !== curType || Number(payload.commentable_id) !== curId) {
                        return;
                    }
                    if (payload.event === 'comment.created' && payload.data?.comment) {
                        handlers.onCreated?.(payload.data.comment);
                    }
                    if (payload.event === 'comment.deleted' && payload.data?.comment_id) {
                        handlers.onDeleted?.(Number(payload.data.comment_id));
                    }
                });
            }

            if (socket.connected) {
                await emitSubscribe(data.token);
            } else {
                await new Promise((resolve) => {
                    socket.once('connect', () => {
                        emitSubscribe(data.token).then(resolve).catch(resolve);
                    });
                });
            }
        } catch {
            /* realtime optional — im lặng khi server tắt */
        }
    }

    function emitSubscribe(token) {
        return new Promise((resolve, reject) => {
            socket.emit('subscribe:comments', { token }, (res) => {
                if (res?.ok) {
                    activeRoom = res.room;
                    resolve();
                } else {
                    reject(new Error(res?.error || 'subscribe_failed'));
                }
            });
        });
    }

    function unsubscribe() {
        if (socket && activeRoom) {
            socket.emit('unsubscribe:comments', { room: activeRoom });
            activeRoom = null;
        }
        if (socket) {
            socket.disconnect();
            socket = null;
        }
        connected.value = false;
    }

    onMounted(() => {
        subscribe();
    });

    watch([commentableType, commentableId], () => {
        unsubscribe();
        subscribe();
    });

    onBeforeUnmount(() => {
        unsubscribe();
    });

    return { connected };
}
