/**
 * Socket.IO server — nhận sự kiện bình luận từ Laravel qua Redis pub/sub.
 *
 * Env: REALTIME_SECRET, REALTIME_REDIS_CHANNEL, REDIS_HOST, REALTIME_SERVER_PORT, APP_URL
 * Chạy: npm run realtime
 */
import { createServer } from 'node:http';
import { createHmac, timingSafeEqual } from 'node:crypto';
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { Server } from 'socket.io';
import Redis from 'ioredis';

function loadDotEnv() {
    const path = resolve(process.cwd(), '.env');
    if (!existsSync(path)) {
        return;
    }
    for (const line of readFileSync(path, 'utf8').split('\n')) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) {
            continue;
        }
        const eq = trimmed.indexOf('=');
        if (eq <= 0) {
            continue;
        }
        const key = trimmed.slice(0, eq);
        if (process.env[key] !== undefined) {
            continue;
        }
        let val = trimmed.slice(eq + 1).trim();
        if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith('\'') && val.endsWith('\''))) {
            val = val.slice(1, -1);
        }
        process.env[key] = val;
    }
    // Một vòng thay ${VAR} (Node không expand như Laravel)
    for (const key of Object.keys(process.env)) {
        const v = process.env[key];
        if (typeof v === 'string' && v.includes('${')) {
            process.env[key] = v.replace(/\$\{([A-Z0-9_]+)\}/g, (_, name) => process.env[name] ?? '');
        }
    }
}

loadDotEnv();

const port = Number(process.env.REALTIME_SERVER_PORT || 6001);
const secret = process.env.REALTIME_SECRET || process.env.APP_KEY || '';
const redisChannel = process.env.REALTIME_REDIS_CHANNEL || 'va-workspace:realtime';
const appUrl = process.env.APP_URL || 'http://localhost';

const redisHost = process.env.REDIS_HOST || '127.0.0.1';
const redisPort = Number(process.env.REDIS_PORT || 6379);
const redisPassword = process.env.REDIS_PASSWORD || undefined;

function verifySubscribeToken(token) {
    if (!token || !secret) {
        return null;
    }
    try {
        const padded = token.replace(/-/g, '+').replace(/_/g, '/');
        const raw = Buffer.from(padded, 'base64').toString('utf8');
        const parts = raw.split(':');
        if (parts.length !== 5) {
            return null;
        }
        const [accountId, type, id, exp, sig] = parts;
        const payload = `${accountId}:${type}:${id}:${exp}`;
        const expected = createHmac('sha256', secret).update(payload).digest('hex');
        const a = Buffer.from(expected, 'utf8');
        const b = Buffer.from(sig, 'utf8');
        if (a.length !== b.length || !timingSafeEqual(a, b)) {
            return null;
        }
        if (Number(exp) < Math.floor(Date.now() / 1000)) {
            return null;
        }
        const allowed = new Set(['feedback', 'blocker', 'task']);
        if (!allowed.has(type)) {
            return null;
        }

        return { accountId: Number(accountId), type, id: Number(id), room: `comments:${type}:${id}` };
    } catch {
        return null;
    }
}

const httpServer = createServer();
const io = new Server(httpServer, {
    cors: {
        origin: appUrl.replace(/\/$/, ''),
        credentials: true,
    },
    path: '/socket.io',
});

io.on('connection', (socket) => {
    socket.on('subscribe:comments', (payload, ack) => {
        const token = payload?.token;
        const parsed = verifySubscribeToken(token);
        if (!parsed) {
            ack?.({ ok: false, error: 'invalid_token' });
            return;
        }
        socket.join(parsed.room);
        ack?.({ ok: true, room: parsed.room });
    });

    socket.on('unsubscribe:comments', (payload) => {
        const room = payload?.room;
        if (room && typeof room === 'string') {
            socket.leave(room);
        }
    });
});

const subscriber = new Redis({
    host: redisHost,
    port: redisPort,
    password: redisPassword || undefined,
});

subscriber.subscribe(redisChannel).then(() => {
    console.log(`[realtime] Subscribed ${redisChannel}`);
}).catch((err) => {
    console.error('[realtime] Redis subscribe failed', err);
    process.exit(1);
});

subscriber.on('message', (_channel, message) => {
    try {
        const payload = JSON.parse(message);
        const room = payload?.room;
        if (!room) {
            return;
        }
        io.to(room).emit('comment', payload);
    } catch (e) {
        console.warn('[realtime] Bad message', e);
    }
});

httpServer.listen(port, () => {
    console.log(`[realtime] Socket.IO on :${port} (CORS ${appUrl})`);
});
