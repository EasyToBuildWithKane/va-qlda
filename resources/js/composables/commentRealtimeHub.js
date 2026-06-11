import axios from 'axios';
import { io } from 'socket.io-client';

const CONNECT_TIMEOUT_MS = 15000;

/** @type {import('socket.io-client').Socket | null} */
let sharedSocket = null;
/** @type {number} */
let socketRefCount = 0;
/** @type {string | null} */
let socketUrlUsed = null;
/** @type {boolean} */
let socketWebsocket = false;
/** @type {Map<string, Set<object>>} */
const handlersByRoom = new Map();
/** @type {Set<string>} */
const joinedRooms = new Set();
/** @type {Map<string, string>} */
const tokenByRoom = new Map();

export function roomNameForComment(type, id) {
    return `comments:${type}:${id}`;
}

function dispatchCommentEvent(payload) {
    if (!payload?.room) {
        return;
    }
    const set = handlersByRoom.get(payload.room);
    if (!set) {
        return;
    }
    for (const handlers of set) {
        if (payload.event === 'comment.created' && payload.data?.comment) {
            handlers.onCreated?.(payload.data.comment);
        }
        if (payload.event === 'comment.updated' && payload.data?.comment) {
            handlers.onUpdated?.(payload.data.comment);
        }
        if (payload.event === 'comment.deleted' && payload.data?.comment_id) {
            handlers.onDeleted?.(Number(payload.data.comment_id));
        }
    }
}

function waitForConnect(sock) {
    if (sock.connected) {
        return Promise.resolve();
    }
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('connect_timeout')), CONNECT_TIMEOUT_MS);
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

function emitSubscribe(sock, token) {
    return new Promise((resolve, reject) => {
        sock.emit('subscribe:comments', { token }, (res) => {
            if (res?.ok) {
                resolve(res.room);
            } else {
                reject(new Error(res?.error || 'subscribe_failed'));
            }
        });
    });
}

async function rejoinAllRooms() {
    if (!sharedSocket?.connected) {
        return;
    }
    for (const room of [...joinedRooms]) {
        const token = tokenByRoom.get(room);
        if (!token) {
            joinedRooms.delete(room);
            continue;
        }
        try {
            await emitSubscribe(sharedSocket, token);
        } catch {
            joinedRooms.delete(room);
        }
    }
}

function socketCacheKey(url) {
    return `${url}|${socketWebsocket ? 'ws' : 'poll'}`;
}

function transportOptions() {
    if (socketWebsocket) {
        return { transports: ['polling', 'websocket'] };
    }
    return { transports: ['polling'], upgrade: false };
}

function ensureSharedSocket(url) {
    const key = socketCacheKey(url);
    if (sharedSocket && socketUrlUsed === key) {
        return sharedSocket;
    }
    if (sharedSocket) {
        sharedSocket.disconnect();
        sharedSocket = null;
        joinedRooms.clear();
    }
    socketUrlUsed = key;
    sharedSocket = io(url, {
        path: '/socket.io',
        ...transportOptions(),
        withCredentials: true,
        reconnection: true,
        reconnectionAttempts: 15,
        reconnectionDelay: 1000,
        reconnectionDelayMax: 8000,
        timeout: 12000,
    });
    sharedSocket.on('comment', dispatchCommentEvent);
    sharedSocket.on('connect', () => {
        rejoinAllRooms().catch(() => {});
    });
    return sharedSocket;
}

function getSharedSocket(url) {
    return ensureSharedSocket(url);
}

export function acquireSharedSocket(url, options = {}) {
    if (options.websocket !== undefined) {
        socketWebsocket = options.websocket === true;
    }
    socketRefCount += 1;
    return ensureSharedSocket(url);
}

export function releaseSharedSocket() {
    socketRefCount = Math.max(0, socketRefCount - 1);
    if (socketRefCount === 0 && sharedSocket) {
        sharedSocket.disconnect();
        sharedSocket = null;
        socketUrlUsed = null;
        joinedRooms.clear();
        tokenByRoom.clear();
        handlersByRoom.clear();
    }
}

export function registerHandlers(room, handlers) {
    if (!handlersByRoom.has(room)) {
        handlersByRoom.set(room, new Set());
    }
    handlersByRoom.get(room).add(handlers);
}

export function unregisterHandlers(room, handlers) {
    const set = handlersByRoom.get(room);
    if (!set) {
        return;
    }
    set.delete(handlers);
    if (set.size === 0) {
        handlersByRoom.delete(room);
        if (sharedSocket && joinedRooms.has(room)) {
            sharedSocket.emit('unsubscribe:comments', { room });
            joinedRooms.delete(room);
            tokenByRoom.delete(room);
        }
    }
}

export async function joinCommentRoom({ url, type, id }) {
    const room = roomNameForComment(type, id);
    if (joinedRooms.has(room) && sharedSocket?.connected) {
        return room;
    }

    const { data } = await axios.get('/realtime/thread-token', {
        params: { type, id },
    });
    if (!data?.token) {
        throw new Error('no_token');
    }

    tokenByRoom.set(room, data.token);
    const sock = getSharedSocket(url);
    await waitForConnect(sock);
    await emitSubscribe(sock, data.token);
    joinedRooms.add(room);

    return room;
}

export function isSocketConnected() {
    return !!sharedSocket?.connected;
}
