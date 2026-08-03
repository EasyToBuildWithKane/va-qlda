---
name: login-page
description: >-
  VA-Workspace login: HRM SSO (opt-in) or Google OAuth UI (VAS mockup), redirect
  sanitization, password login for E2E/PHPUnit only. Use when editing Login.vue,
  HrmSsoController, GoogleAuthController, LoginController, routes auth, login
  assets, or auth tests.
---

# Login page (VA-Workspace)

Đọc **`.cursor/rules/login-page.mdc`** trước khi sửa.

## Quick checklist

- UI **không** form password trên trang (`resources/js/Pages/Auth/Login.vue`).
- **Hai chế độ nút** (`LoginController`) — cùng UI icon Google (mockup VAS):
  - `HRM_SSO_ENABLED=true` + `HRM_SSO_BASE_URL` → icon Google → `GET /auth/hrm` → HRM `/sso/authorize` → `GET /auth/hrm/callback` (JWT RS256 / JWKS).
  - SSO tắt → icon Google → `GET /auth/google` (`googleEnabled` khi có cả `GOOGLE_CLIENT_ID` + `GOOGLE_CLIENT_SECRET`); khi `false` → `href="#"` + `preventDefault`.
- **Giao diện:** nền `#9a0036`; watermark `background-logo.png` với `brightness-0 invert` + `opacity-100`; thẻ trắng: tiêu đề «Đăng nhập», copy «Đăng nhập thông qua tài khoản mail do nhà trường cung cấp», nút Google tròn (`p-2`, icon `h-9`/`h-10`).
- **SSO:** `HrmSsoController` + `HrmSsoJwtVerifier` — verify `aud=workspace`, `iss`, `exp`; map `employee_uuid` → `employees.hrm_employee_uuid` (fallback email / `HrmIdentityResolver`). JWT SSO ≠ `HRM_API_TOKEN` (M2M).
- **Google (SSO tắt):** `GET /auth/google` → OAuth (`prompt=select_account`, optional `hd`) → `GET /auth/google/callback` → session guard `system`.
- Email khớp `employees.email` (active) → refresh từ HRM API; nếu chưa có → `ensureEmployeeByEmail` (API) lazy upsert + provision `SystemAccount`.
- **Identity:** chỉ `HrmApiClient` (`HRM_API_BASE_URL` + `HRM_API_TOKEN`) — không đọc DB `va_hrm`, không `HRM_IDENTITY_SOURCE` / fallback DB. Smoke: `php artisan hrm:api-ping`.
- Domain: `GOOGLE_ALLOWED_DOMAINS` (comma-separated, `config/va.php`).
- Redirect: **`LoginRedirectSanitizer`** — chặn `/login`, `/auth/google`, `/auth/hrm`, URL ngoài.
- Production: `AUTH_PASSWORD_LOGIN=false` — POST `/login` 404; dev/E2E: `true`.
- E2E: `tests/e2e/helpers/loginPost.js` + `auth.js` — POST `/login`, không click UI. Playwright ép `AUTH_PASSWORD_LOGIN=true`.
- Env SSO: `HRM_SSO_*` (`config/services.php`) — **bật sau** khi API M2M ổn; callback `{APP_URL}/auth/hrm/callback`.
- Env API M2M: `HRM_API_BASE_URL`, `HRM_API_TOKEN` — **không** dùng JWT SSO làm Bearer.

## Verify

```bash
php artisan config:clear
php artisan route:list --name=auth
php artisan hrm:api-ping
php artisan test --filter=HrmIdentityResolverTest
php artisan test --filter=HrmSsoLoginTest
php artisan test --filter=LoginTest
npm run test:e2e -- tests/e2e/auth.spec.js
```

Visual regression (tùy chọn): `tests/e2e/visual/feature-screens.spec.js` — cập nhật snapshot khi đổi layout login.
