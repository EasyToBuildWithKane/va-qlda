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
| Feature | `tests/Feature/` | Login, Project, Task, Blocker, Department, Feedback, DailyReport, **Notification** |

`DailyReportTest` (20 test) phủ thêm: `summary.trend`/`completion_rate`, lọc nhiều người (`employee_ids[]`), endpoint `export-data` (toàn bộ kết quả lọc + self-scoping member).

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
| `chromium` | `npm run test:e2e` | Luồng UI (CI) — bỏ qua `visual/`, `smoke/` |
| `visual` | `npm run test:e2e:visual` | Snapshot regression (TD-020) |
| `smoke` | `npm run test:e2e:smoke` | Chụp full-page thủ công (không so snapshot CI) |

### Specs hiện có

| File | Coverage |
|------|----------|
| `auth.spec.js` | Login, dashboard, invalid creds |
| `projects.spec.js` | List, summary, create (admin), **project show + Sprint tab** |
| `blockers.spec.js` | List, quyền, guest redirect |
| `daily-report.spec.js` | Luồng báo cáo ngày |
| `notifications.spec.js` | Bell UI, JSON unread-count |
| `visual/feature-screens.spec.js` | Snapshot từng màn hình (login, member, admin tour + project show) |

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

Visual regression: `npm run test:e2e:visual` — so với baseline trong `tests/e2e/visual/snapshots/`. Đổi UI cố ý: `--update-snapshots`. Helper: `tests/e2e/helpers/visualCapture.js`.

> ⚠️ Redesign `/daily-reports` (2026-06) đổi toàn bộ giao diện → baseline `admin-daily-reports-history.png` đã lỗi thời. Chạy lại `npm run test:e2e:visual --update-snapshots` và commit ảnh mới trước khi job `playwright` xanh.

---

## Artifacts

| Path | Nội dung |
|------|----------|
| `playwright-report/` | HTML report |
| `test-results/` | Screenshot/video/trace khi fail |
| `tests/e2e/visual/snapshots/` | Baseline ảnh (commit vào git) |
