---
name: login-page
description: >-
  Documents VA Dispatch login (Google OAuth SPA, redirect sanitization, post-login
  routing). Use when editing LoginView, loginRedirect, GoogleAuthController, router
  auth guards, or OAuth/PWA navigation issues.
---

# Login page (VA Dispatch)

Đọc **[login-page.mdc](login-page.mdc)** trước khi sửa luồng đăng nhập.

## Quick checklist

- UI chỉ **Google OAuth** (`/auth/google`), không form email/password trên `LoginView.vue`.
- Callback: Laravel redirect `/login?token=…&redirect=…` → `LoginView` gọi `auth.setToken` + `fetchMe` → `resolvePostLoginTarget`.
- Mọi `redirect` phải qua **`sanitizeLoginRedirect`** (FE) / **`sanitizePostLoginRedirect`** (BE) — chặn open redirect và lồng `/auth/google`.
- PWA: **`sw.js`** denylist `/auth/google` — không đổi trừ khi hiểu hậu quả redirect loop.
- Đích mặc định theo role: portal-only → `/portal`; driver-only → `/driver`; dept-head-only → `/dept`; staff → `/mng` (`DISPATCH_WEB_BASE`).
