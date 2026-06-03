---
name: backend-code-review
description: >-
  Reviews staged or unstaged backend code against ISC Technical Standards plus
  common security and performance issues (SQL injection, IDOR, mass assignment,
  N+1, transactions). Runs git diff, outputs Pass/Improve/Critical table with
  file lines and fixes. Use when the user asks for backend code review, PR
  review, ISC review, or before merge/commit on PHP/Laravel changes.
---

# Backend Code Review — ISC + Security/Performance

## 1. Lấy diff

Chạy **song song** (PowerShell: `;` thay `&&`):

```bash
git diff --staged
git status
```

- Có staged → review **chỉ** `--staged`.
- Không staged → chạy `git diff HEAD`, báo user đang review toàn bộ thay đổi chưa stage.

Nếu diff rỗng → dừng, báo không có gì để review.

User có thể thêm focus qua lời nhắn (vd. chỉ `TaskController`, chỉ security) — áp dụng khi phân tích.

## 2. Phạm vi file

Ưu tiên: `app/`, `routes/`, `database/migrations/`, `tests/`. Bỏ qua lockfile/vendor trừ khi diff có secrets.

Map checklist theo **Laravel** — xem `.cursor/rules/backend-code-review-isc.mdc`.

## 3. Review theo hai lớp

| Lớp | Nội dung |
|-----|----------|
| **A — ISC** | Đặt tên, format, comment, bảo mật, SOLID/DRY/KISS/DI, API naming, response, error code, timeout |
| **B — Lỗi thường gặp** | SQLi, IDOR, mass assignment, SSRF, secrets, CSRF, N+1, index, transaction, race, idempotency, rate limit, log PII, circuit breaker, pagination, error leak |

Checklist đầy đủ: [reference.md](reference.md)

**VA-QLDA:** Inertia web chủ đạo — ISC 3.x JSON envelope áp dụng cho endpoint JSON (`NotificationController`, tương lai `api.php`); Inertia redirect + `with('success'|'error')` là hợp lệ.

## 4. Format kết quả (bắt buộc)

### Tổng quan

- Số file, loại thay đổi (feat/fix/refactor…)
- **Pass** / **Cần cải thiện** / **Có vấn đề nghiêm trọng**

### Vấn đề phát hiện

| Mức | Ký hiệu | Ý nghĩa |
|-----|--------|---------|
| Critical | 🔴 | Bảo mật, data loss, secret hardcode |
| Warning | 🟡 | Convention, error handling, performance |
| Suggestion | 🔵 | Readability, chất lượng |

Mỗi issue gồm:

1. File + dòng (trích từ diff)
2. Tiêu chuẩn (ISC x.x / OWASP / Laravel)
3. Gợi ý fix (PHP/Laravel, có code snippet ngắn)

### Điểm tốt

Liệt kê điều đúng chuẩn.

### Git & commit checklist

- [ ] Không `dd()` / `dump()` / debug sót
- [ ] Không code comment-out thừa
- [ ] Không credential trong source
- [ ] Scope đúng branch/task
- [ ] PHP: `vendor/bin/pint --test` (CI blocking)
- [ ] Upload/media: `PublicMediaUrl` hoặc route file có `authorize`; không URL tới file orphan

## 5. Hành vi

- **Chỉ review** — không sửa code trừ khi user yêu cầu fix.
- Không kết luận Pass nếu còn 🔴 chưa giải thích.
- Ưu tiên vấn đề trong **diff**, không lecture toàn repo.
