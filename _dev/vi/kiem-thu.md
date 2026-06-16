# Kiểm thử — PHPUnit & Playwright (tiếng Việt)

**File gốc:** [`../testing.md`](../testing.md)

---

## PHPUnit (backend)

```bash
composer test
php artisan test --filter=TaskTest
```

| Suite | Đường dẫn |
|-------|-----------|
| Unit | `tests/Unit/` |
| Feature | `tests/Feature/` — Login, Project, Task, Blocker, DailyReport, Notification, KB, AI, … |

**CI:** job `backend-tests` — Pint trước, rồi PHPUnit.

**Stub Vite:** `tests/TestCase.php` tạo `public/build/manifest.json` tối thiểu nếu thiếu — tránh Inertia 500 khi chưa `npm run build`.

**DailyReport:** test phủ `summary.trend`, lọc `employee_ids[]`, endpoint JSON `export-data`.

---

## Playwright E2E

Config: `playwright.config.js` · thư mục `tests/e2e/`.

### Cài đặt

```bash
npm run test:e2e:install
```

Cần Node 20+, PHP trong PATH, `composer install` đã xong.

### Project / lệnh

| Project | Lệnh | Mô tả |
|---------|------|--------|
| `chromium` | `npm run test:e2e` | Luồng UI — **CI**; bỏ qua `visual/`, `smoke/` |
| `visual` | `npm run test:e2e:visual` | Snapshot regression (TD-020) |
| — | `npm run test:e2e:visual:update` | Cập nhật baseline sau đổi UI |
| — | `npm run test:e2e:smoke` | Chụp full-page (không so CI) |

### Spec hiện có (tham chiếu)

| File | Phạm vi |
|------|---------|
| `auth.spec.js` | Login, dashboard, sai mật khẩu |
| `projects.spec.js` | Danh sách, tạo (admin), project show + Sprint |
| `blockers.spec.js` | List, quyền, guest redirect |
| `departments.spec.js` | CRUD cơ bản |
| `daily-report.spec.js` | Luồng báo cáo ngày |
| `notifications.spec.js` | Bell, JSON unread-count |
| `knowledge-coaching.spec.js` | Smoke KB / Coaching |
| `visual/feature-screens.spec.js` | Snapshot màn hình |

**Auth helper:** `tests/e2e/helpers/auth.js` — fixture đã login (`member` \| `admin` \| `lead` \| `viewer`).

**Seed:** `member` / `password`, `admin` / `password`.

### Chạy

```bash
npm run test:e2e
npm run test:e2e:ui
npx playwright test tests/e2e/projects.spec.js
npx playwright test --grep "login"
npx playwright test --headed --debug
npx playwright show-report
```

**Web server:** Playwright tự `php artisan serve :8000`; local có thể reuse server đang chạy.

**Retry / workers:** 0 retry local; CI retry 2, `workers: 1`.

### Visual regression

Baseline: `tests/e2e/visual/snapshots/`. Đổi UI cố ý:

```bash
npm run test:e2e:visual -- --update-snapshots
```

Commit ảnh mới cùng PR. Sau redesign lớn (vd. daily-reports) cần update baseline trước khi CI xanh.

Helper: `tests/e2e/helpers/visualCapture.js`.

---

## Tích hợp CI

Job `playwright` sau `backend-tests` + `frontend-build`:

1. `npm run build`
2. SQLite + `migrate:fresh --seed`
3. `npm run test:e2e`

**Pre-push local:** mặc định **không** chạy E2E; CI vẫn bắt buộc pass.

---

## Artifact

| Path | Nội dung |
|------|------------|
| `playwright-report/` | HTML (gitignore) |
| `test-results/` | screenshot/video/trace khi fail |
| `tests/e2e/visual/snapshots/` | **Commit** baseline ảnh |

---

## Viết spec mới

```js
import { test, expect } from '@playwright/test';

test('mo ta', async ({ page }) => {
    await page.goto('/duong-dan');
    await expect(page.getByRole('heading', { name: 'Tieu de' })).toBeVisible();
});
```

Ưu tiên `getByRole`, `getByLabel` — ổn định hơn CSS. Đổi UI → sửa selector trong cùng PR.

Login POST: dùng `tests/e2e/helpers/loginPost.js` nếu cần tránh 419 CSRF.

---

## Liên quan

- [ci-cd.md](ci-cd.md) — thứ tự job
- [loi-thuong-gap.md](loi-thuong-gap.md) — Playwright fail, port, SQLite
