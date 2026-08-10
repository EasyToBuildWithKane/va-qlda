# Realtime comments (Socket.IO) — VA-Workspace

Trao đổi trên **test case**, **task**, bug, feedback: hai người cùng mở một thread sẽ thấy bình luận mới qua WebSocket (nếu stack realtime đang chạy). Nếu realtime tắt hoặc lỗi, người gửi vẫn thấy tin sau partial reload Inertia (`only: ['blockers']`); người còn lại cần refresh hoặc sửa realtime.

## Architecture

```
Browser A ──POST /comments──► Laravel ──Redis PUBLISH──► Node (realtime/server.mjs) ──Socket.IO──► Browser B
                │                                              ▲
                └── Inertia partial reload (poster only)         └── room: comments:blocker:{id}
```

| Piece | Path / command |
|-------|----------------|
| Publish | `App\Support\Realtime\CommentRealtimePublisher` |
| Subscribe token | `GET /realtime/thread-token` |
| Client | `resources/js/composables/useCommentRealtime.js`, hub `commentRealtimeHub.js` (một Socket.IO dùng chung) |
| UI | `CommentThread.vue`, `TaskDetailCollaboration.vue` — optimistic + badge **Realtime** |
| Node server | `realtime/server.mjs` — `npm run realtime` |

Shared Inertia props: `realtime.enabled`, `realtime.url` (`REALTIME_CLIENT_URL`).

## Local dev (2 browsers)

1. `.env`:

   ```env
   REALTIME_ENABLED=true
   REALTIME_SECRET="${APP_KEY}"
   REALTIME_REDIS_CHANNEL=va-workspace:realtime
   REALTIME_CLIENT_URL=http://127.0.0.1:8000
   REALTIME_SERVER_PORT=6001
   REDIS_HOST=127.0.0.1
   REDIS_PORT=6379
   ```

2. Start **Redis** (required for Laravel → Node bridge).

3. Three processes:

   ```bash
   php artisan serve
   npm run dev
   npm run realtime
   ```

4. Local Socket.IO: either proxy `/socket.io` to `:6001` or set `REALTIME_CLIENT_URL=http://127.0.0.1:6001` (CORS must allow your Laravel origin — see `realtime/server.mjs`).

5. Two accounts → `/blockers` → cùng test case → tab **Trao đổi**. Badge **Realtime** (xanh) = đã join room. User A gửi → User B thấy ngay (không F5).

## Production checklist (projects.vaschools.edu.vn)

### 1. Environment

```env
REALTIME_ENABLED=true
REALTIME_SECRET="${APP_KEY}"          # must match Node process
REALTIME_REDIS_CHANNEL=va-workspace:realtime
REALTIME_CLIENT_URL=https://projects.vaschools.edu.vn
REALTIME_SERVER_PORT=6001
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

- `REALTIME_CLIENT_URL` = URL trình duyệt dùng để mở Socket.IO (thường **cùng domain** với app, qua reverse proxy).
- Laravel **must** reach Redis (`Redis::publish`). Install **phpredis** (or `predis/predis`) and run `redis-server`.

### 2. Node realtime service (always on)

```bash
cd /path/to/va-workspace
npm ci --omit=dev   # includes socket.io, ioredis
```

**systemd** example (`/etc/systemd/system/va-workspace-realtime.service`):

```ini
[Unit]
Description=VA-Workspace Socket.IO realtime
After=network.target redis.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/va-workspace
Environment=NODE_ENV=production
ExecStart=/usr/bin/node realtime/server.mjs
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now va-workspace-realtime
sudo journalctl -u va-workspace-realtime -f
```

Expect log: `[realtime] Subscribed va-workspace:realtime` and `Socket.IO on :6001`.

### 3. Nginx — proxy WebSocket (required if client URL is main domain)

Without this, browser connects to `https://projects…/socket.io` but PHP-FPM cannot serve Socket.IO → **realtime silently fails**.

```nginx
location /socket.io/ {
    proxy_pass http://127.0.0.1:6001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 86400;
}
```

Reload nginx, then verify:

```bash
curl -I "https://projects.vaschools.edu.vn/socket.io/?EIO=4&transport=polling"
```

Should not be `404` from Laravel.

### 3b. OpenLiteSpeed / LiteSpeed (VA production)

External processor + context (path `/socket.io/` → `127.0.0.1:6001`) is the **correct pattern** for VA Workspace (`REALTIME_CLIENT_URL` = same vhost).

Example (align names with your vhost):

```
extprocessor va_workspace_realtime {
  type                    proxy
  address                 127.0.0.1:6001
  maxConns                50
  initTimeout             60
  retryTimeout            0
  respBuffer              0
}

context /socket.io/ {
  type                    proxy
  handler                 va_workspace_realtime
  addDefaultCharset       off
  allowBrowse             0
}
```

**Also required:**

1. **Virtual Host → General → Enable WebSocket = Yes** (otherwise upgrade fails; client may stick to long-polling or fail silently).
2. Process **`node realtime/server.mjs`** listening on `6001` (systemd).
3. **Redis** + `REALTIME_ENABLED=true` on Laravel.
4. Graceful reload vhost after edit.

**Verify:**

```bash
curl -sS "https://projects.vaschools.edu.vn/socket.io/?EIO=4&transport=polling" | head -c 120
# Expect Socket.IO engine payload (starts with digit `0`), not Laravel HTML 404
ss -lntp | grep 6001
redis-cli ping
```

Browser: tab Trao đổi test case → badge **Realtime** (xanh) after deploy frontend with `useCommentRealtime` indicator.

**Console: `WebSocket connection … transport=websocket&sid=…` failed**

Polling đã OK (có `sid`), chỉ **upgrade WebSocket** qua proxy bị lỗi. Realtime vẫn có thể chạy qua long-polling.

- Production mặc định: `REALTIME_WEBSOCKET=false` (client không thử `wss`).
- Sau khi bật **Enable WebSocket** + test `wss` OK trên LiteSpeed: `REALTIME_WEBSOCKET=true` + `php artisan config:clear` + build frontend.

`allowBrowse 0` — no directory listing on `/socket.io/` (optional hardening).

### 4. Verify end-to-end

| Step | Check |
|------|--------|
| Token API | Logged-in GET `/realtime/thread-token?type=blocker&id=1` → `200` + `token` |
| UI | Tab Trao đổi shows green **Realtime** badge |
| Redis | `redis-cli SUBSCRIBE va-workspace:realtime` → post comment → JSON message |
| Two users | B sees A's comment without reload |

### 5. Fallback behaviour

- `REALTIME_ENABLED=false` → no Socket.IO; partial reload after send on blocker pages.
- Redis down → Laravel logs `Comment realtime publish failed`; no live updates for other users.
- Node down → no **Realtime** badge; poster still sees own comment via Inertia reload.

## Security

- Subscribe requires signed token (`ThreadSubscribeToken`) + policy (`CommentThreadAuthorizer`).
- Node verifies HMAC with `REALTIME_SECRET` / `APP_KEY`.
- CORS on Node limited to `APP_URL`.

## Related

- `_dev/commands.md` — `npm run realtime`
- `_dev/vi/realtime.md` — bản tiếng Việt
