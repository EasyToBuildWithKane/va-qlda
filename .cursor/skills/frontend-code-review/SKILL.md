---
name: frontend-code-review
description: >-
  Reviews staged or unstaged frontend code (Vue 3, Inertia, JavaScript) against
  ISC Technical Standards plus common web security and performance issues (XSS,
  token storage, CSRF, race conditions, re-renders). Runs git diff and outputs
  Pass/Improve/Critical with file lines and fixes. Use for frontend code review,
  PR review, ISC FE review, or before merge on resources/js changes.
---

# Frontend Code Review — ISC + Security/Performance

## 1. Lấy diff

```bash
git diff --staged
git status
```

- Có staged → review `--staged` only.
- Không staged → `git diff HEAD`, báo user.
- Diff rỗng → dừng.

User có thể thêm focus (vd. chỉ `NotificationCenterDrawer.vue`) — áp dụng khi review.

## 2. Phạm vi

`resources/js/**` (`.vue`, `.js`), `resources/css/**`. Bỏ qua `public/build`, vendor.

**Stack:** Vue 3 Composition API + Inertia — map React ví dụ trong reference sang Vue (xem rule `frontend-code-review-isc.mdc`).

## 3. Hai lớp review

| Lớp | Nội dung |
|-----|----------|
| **A — ISC FE** | Đặt tên, style, comment, bảo mật, API consumer, timeout/abort |
| **B — Lỗi thường gặp** | XSS, token storage, CSRF, open redirect, secrets in bundle, CSP/eval, state, re-render, race/cleanup, a11y, loading/error, form validation |

Checklist đầy đủ: [reference.md](reference.md)

**Inertia:** Không bắt buộc envelope `success/data/error/meta` trên mọi page — áp dụng cho axios JSON; form errors qua `form.errors` + flash.

## 4. Format kết quả (bắt buộc)

### Tổng quan

- Số file, loại thay đổi
- **Pass** / **Cần cải thiện** / **Có vấn đề nghiêm trọng**

### Vấn đề phát hiện

| Mức | Ký hiệu |
|-----|--------|
| Critical | 🔴 |
| Warning | 🟡 |
| Suggestion | 🔵 |

Mỗi issue: (1) file + dòng từ diff (2) ISC/OWASP (3) fix gợi ý Vue/Inertia.

### Điểm tốt

### Git & commit checklist

- [ ] Không `console.log` / `debugger`
- [ ] Không code comment-out thừa
- [ ] Không credential / API key trong source
- [ ] Scope đúng task/branch

## 5. Hành vi

- Chỉ review — không sửa trừ khi user yêu cầu.
- Không Pass nếu còn 🔴 chưa nêu.
- Ưu tiên vi phạm **trong diff**.
