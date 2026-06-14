# Tài liệu tiếng Việt — `_dev/vi/`

Thư mục này dùng để **giải thích bằng tiếng Việt** — onboarding, quy trình nội bộ, thuật ngữ, ghi chú team.

## Quy ước hai lớp

| Lớp | Thư mục | Vai trò |
|-----|---------|---------|
| **Chuẩn (canonical)** | `_dev/*.md` (gốc) | Lệnh CLI chính xác, tên job CI, config thật từ repo |
| **Giải thích (VI)** | `_dev/vi/*.md` | Diễn giải, hướng dẫn cho dev Việt Nam, FAQ |

**Khi cập nhật:** sửa file gốc tiếng Anh trước (script mới, hook mới, job CI mới) → rồi cập nhật file VI tương ứng.

## Mục lục đầy đủ

| File VI | Nội dung | File gốc (EN) |
|---------|----------|---------------|
| [tong-quan.md](tong-quan.md) | `_dev/` là gì, lệnh hay dùng, luồng làm việc | [../README.md](../README.md) |
| [lenh-cli.md](lenh-cli.md) | Tất cả lệnh npm, artisan, Husky, Playwright | [../commands.md](../commands.md) |
| [quy-trinh.md](quy-trinh.md) | Dev hàng ngày, feature, PR, deploy, hotfix | [../workflows.md](../workflows.md) |
| [quy-uoc.md](quy-uoc.md) | Commit, branch, Vue, PHP, ESLint | [../conventions.md](../conventions.md) |
| [ci-cd.md](ci-cd.md) | GitHub Actions — job, trigger, skip CI | [../ci-cd.md](../ci-cd.md) |
| [kiem-thu.md](kiem-thu.md) | Playwright E2E — setup, chạy test, viết spec | [../testing.md](../testing.md) |
| [loi-thuong-gap.md](loi-thuong-gap.md) | Husky, commitlint, ESLint, CI, npm, Vite | [../troubleshooting.md](../troubleshooting.md) |
| [realtime.md](realtime.md) | Trao đổi Socket.IO — dev 2 người, deploy production | [../realtime.md](../realtime.md) |
| *(rule Cursor)* | Đồng bộ `docs/` + `_dev/` khi đổi code | [../../.cursor/rules/docs-sync.mdc](../../.cursor/rules/docs-sync.mdc) |

## AI / Cursor / Claude

- Câu hỏi **lệnh, config, CI chính xác** → đọc file gốc `_dev/*.md` trước.
- User hỏi **bằng tiếng Việt cần giải thích** → đọc `_dev/vi/` tương ứng.
- Thiếu nội dung → trả lời và đề xuất cập nhật cả file EN lẫn VI.
