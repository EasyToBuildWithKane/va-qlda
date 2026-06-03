# Testing — PHPUnit & Playwright

---

## PHPUnit

`tests/TestCase.php` seeds a minimal `public/build/manifest.json` when missing so Inertia `@vite` does not 500 in the CI PHPUnit job (no `npm run build` in that job).

```bash
composer test
php artisan test --filter=TaskTest
```

| Suite | Path | Ghi chú |
|-------|------|---------|
| Unit | `tests/Unit/` | ScoringService |
| Feature | `tests/Feature/` | Login, Project, Task, Blocker, Bug, Department, Feedback, DailyReport, **Notification** |

**CI:** job `backend-tests` trong `.github/workflows/ci.yml`.

---

## Playwright E2E

Config: `playwright.config.js` · Thư mục: `tests/e2e/`.

### Setup

```bash
npm run test:e2e:install
```

### Projects

| Project | Lệnh | Mô tả |
|---------|------|--------|
| `chromium` | `npm run test:e2e` | Luồng UI (CI) — bỏ qua `visual/` |
| `visual` | `npm run test:e2e:visual` | Snapshot regression (TD-020) |

### Specs hiện có

| File | Coverage |
|------|----------|
| `auth.spec.js` | Login, dashboard, invalid creds |
| `projects.spec.js` | List, summary, create (admin), **project show + Sprint tab** |
| `blockers.spec.js` | List, quyền, guest redirect |
| `bugs.spec.js` | List, quyền |
| `departments.spec.js` | List, CRUD cơ bản |
| `daily-report.spec.js` | Luồng báo cáo ngày |
| `notifications.spec.js` | Bell UI, JSON unread-count |
| `visual/screenshots.spec.js` | Login, dashboard, projects index |

Auth helper: `tests/e2e/helpers/auth.js` — fixture `page` đã login (`role`: member | admin | lead | viewer).

Credentials (seed): `member` / `password`, `admin` / `password`.

### Chạy

```bash
npm run test:e2e
npm run test:e2e:visual
npm run test:e2e:ui
npx playwright test tests/e2e/projects.spec.js
npx playwright test --project=visual --update-snapshots
```

### CI

Job `playwright` chạy sau PHPUnit + `npm run build` → `npm run test:e2e` (chỉ chromium).

Visual regression: chạy local hoặc thêm job riêng khi snapshot đã commit.

---

## Artifacts

| Path | Nội dung |
|------|----------|
| `playwright-report/` | HTML report |
| `test-results/` | Screenshot/video/trace khi fail |
| `tests/e2e/visual/snapshots/` | Baseline ảnh (commit vào git) |
