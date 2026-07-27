---
name: login-page
description: >-
  VA-QLDA login: Google OAuth UI (VAS mockup), redirect sanitization, password
  login for E2E/PHPUnit only. Use when editing Login.vue, GoogleAuthController,
  LoginController, routes auth, login assets, or auth tests.
---

# Login page (VA-QLDA)

Đọc **`.cursor/rules/login-page.mdc`** trước khi sửa.

## Quick checklist

- UI **chỉ** nút Google (`resources/js/Pages/Auth/Login.vue`) — không form password trên trang.
- **Nút Google:** icon `/images/google.png` luôn render; `googleEnabled` từ `LoginController` (cả `GOOGLE_CLIENT_ID` + `GOOGLE_CLIENT_SECRET`); khi `false` → `href="#"` + `preventDefault`, gợi ý cấu hình `.env`.
- **Giao diện:** nền `#9a0036`; watermark `background-logo.png` với `brightness-0 invert` + `opacity-100` (file PNG gốc gần đen — `invert` rồi full opacity).
- OAuth: `GET /auth/google` → Google → `GET /auth/google/callback` → session guard `system`.
- Email Google phải khớp `employees.email` (active) và có `SystemAccount` active.
- Domain: `GOOGLE_ALLOWED_DOMAINS` (comma-separated, `config/va.php`).
- Redirect query/session: **`LoginRedirectSanitizer`** — không open redirect, không lồng `/auth/google`.
- Production: `AUTH_PASSWORD_LOGIN=false` — POST `/login` 404; dev/E2E: `true`.
- E2E: `tests/e2e/helpers/loginPost.js` (CSRF) + `auth.js` — POST `/login`, không click UI Google. Playwright `webServer.env` ép `SESSION_DRIVER=file`, `AUTH_PASSWORD_LOGIN=true`.
- Cấu hình: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` (= `{APP_URL}/auth/google/callback`).

## Verify

```bash
php artisan config:clear
php artisan route:list --name=auth
php artisan test --filter=LoginTest
npm run test:e2e -- tests/e2e/auth.spec.js
```

Visual regression (tùy chọn): `tests/e2e/visual/feature-screens.spec.js` — cập nhật snapshot khi đổi layout login.
