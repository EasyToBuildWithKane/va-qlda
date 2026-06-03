---
name: ship-ready
description: >-
  Runs VA-QLDA pre-push quality gates (Pint, ESLint, PHPUnit, optional E2E)
  and fixes common CI failures (Vite manifest, Pint import order, missing
  storage files). Use before git push, when CI fails, or when user asks to
  prepare a merge-ready commit.
---

# Ship-ready — VA-QLDA

## 1. Xác định phạm vi thay đổi

```bash
git status
git diff --stat
```

Chỉ chạy bước liên quan file đã sửa (PHP / JS / cả hai).

## 2. Gates (khớp CI)

**PHP thay đổi:**

```bash
vendor/bin/pint --test
vendor/bin/pint
php artisan test
```

**Frontend thay đổi:**

```bash
npm run lint
npm run build
```

**Trước push (khuyến nghị):**

```bash
npm run test:e2e
```

Hoặc `git push` và để Husky pre-push chạy (cần Playwright chromium: `npm run test:e2e:install`).

## 3. Lỗi CI thường gặp → xử lý

| Triệu chứng | Nguyên nhân | Fix |
|-------------|-------------|-----|
| Pint `unary_operator_spaces` / import order | Chưa chạy Pint | `vendor/bin/pint` rồi commit |
| PHPUnit 500 trên GET Inertia | Thiếu Vite manifest trên runner | Đã có stub trong `tests/TestCase` — không xóa; không phụ thuộc `npm build` trong job PHPUnit |
| E2E login kẹt / 404 storage | Server :8000 sai DB hoặc file mất | Playwright config; `storage:link` + file thật hoặc xóa bản ghi orphan |
| ESLint fail | Staged chưa fix | `npm run lint:fix` |

## 4. Media / attachment (regression)

Khi sửa upload hoặc preview tài liệu:

- Backend: `PublicMediaUrl`, route `projects.attachments.file`, `ProjectAttachment::fileExists()`.
- Frontend: không `fetch` khi `!file.url`; message tiếng Việt.

## 5. Commit

- Message: `type(scope): mô tả` (commitlint).
- Không `--no-verify` trừ khi user yêu cầu.
- Tách commit theo module nếu diff lớn.

Tham chiếu: `_dev/ci-cd.md`, `_dev/workflows.md`, `.cursor/rules/ci-quality-gates.mdc`.
