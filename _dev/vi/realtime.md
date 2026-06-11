# Trao đổi realtime (Socket.IO)

Giải thích triển khai — lệnh và config chuẩn: [`../realtime.md`](../realtime.md).

## Hai người có thấy ngay không?

**Có**, khi đủ 4 lớp:

1. `.env`: `REALTIME_ENABLED=true`, Redis chạy, `REALTIME_SECRET` trùng giữa Laravel và Node.
2. Process Node: `npm run realtime` (production: systemd / PM2, luôn bật).
3. Nginx (hoặc proxy) chuyển `/socket.io/` → `127.0.0.1:6001`.
4. Cả hai user mở **cùng** vướng mắc → tab **Trao đổi** → thấy badge **Realtime** (xanh).

Người gửi luôn thấy tin của mình nhờ tải lại phần `blockers` (Inertia). Người còn lại **cần realtime**; nếu không, phải F5.

## Kiểm tra nhanh production

1. DevTools → Network → WS: có kết nối tới `…/socket.io/`.
2. Tab Trao đổi có badge **Realtime**.
3. Server: `journalctl -u va-qlda-realtime -f` khi có người gửi bình luận (Redis publish → Node emit).

## Lỗi thường gặp

| Triệu chứng | Nguyên nhân | Xử lý |
|-------------|-------------|--------|
| Không badge Realtime | `REALTIME_ENABLED=false` hoặc token 404 | Bật env, `config:cache` |
| WS 404 HTML Laravel | Thiếu proxy `/socket.io` | Thêm block nginx (xem EN doc) |
| Một người thấy, một không | Node/Redis tắt | `systemctl status`, `redis-cli ping` |
| Mất realtime sau vài phút | Mạng ngắt — client tự reconnect | Đã cải thiện: join lại room sau reconnect |

## Dev local

Redis + `php artisan serve` + `npm run dev` + `npm run realtime`. Chi tiết: [`../realtime.md`](../realtime.md).
