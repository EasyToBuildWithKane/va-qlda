# CLI commands — VA-Workspace

All commands run from the project root unless noted.

---

## Development

| Command | Description |
|---------|-------------|
| `npm run dev` | Start Vite dev server (HMR for `resources/js/` and `resources/css/app.css`) |
| `php artisan serve` | Start Laravel backend on `http://127.0.0.1:8000` |
| `php artisan migrate` | Run pending migrations |
| `php artisan migrate:fresh --seed` | Drop all tables, re-run migrations, seed data |

**Typical local setup:** two terminals — `npm run dev` + `php artisan serve`.

Vite entry points (from `vite.config.js`): `resources/css/app.css`, `resources/js/app.js`.  
Import alias: `@` → `resources/js/`.

---

## Build & assets

| Command | Description |
|---------|-------------|
| `npm run build` | Production Vite build → `public/build/` |
| `npm run preview` | Preview production build locally *(not defined in `package.json`; use `vite preview` if needed)* |
| `npm run realtime` | Socket.IO server for live comment threads (requires Redis) |
| `npm run realtime:dev` | Same with `--watch` on `realtime/server.mjs` |

---

## Linting & formatting

| Command | Description |
|---------|-------------|
| `npm run lint` | ESLint on `resources/js/` — `--max-warnings=0` (warnings fail) |
| `npm run lint:fix` | ESLint with auto-fix |
| `npx lint-staged` | Lint **staged** files only (same rules as pre-commit hook) |
| `composer format` | Laravel Pint — fix PHP style |
| `composer format:test` | Pint dry-run (`--test`) |
| `composer analyse` | PHPStan static analysis |

**lint-staged config** (`package.json`): staged `resources/js/**/*.{js,ts,jsx,tsx,vue}` → `eslint --fix --max-warnings=0`.

---

## Git hooks (Husky)

Hooks live in `.husky/`. Installed via `npm run prepare` → `node node_modules/husky/bin.js`.

| Hook | Trigger | What it does |
|------|---------|--------------|
| **pre-commit** | `git commit` (before commit is created) | Runs `lint-staged` — ESLint auto-fix on staged JS/Vue files |
| **commit-msg** | `git commit` (after message written) | Runs `@commitlint/cli --edit` — validates Conventional Commits format |
| **prepare-commit-msg** | `git commit` (before editor opens) | Runs `scripts/prepare-commit-msg.mjs` — auto-suggests commit message from staged diff (skipped for merge/squash/commit or if user already typed a message) |
| **pre-push** | `git push` | **Mặc định:** bỏ qua E2E (Sync nhanh). Bật local: `RUN_E2E_ON_PUSH=1 git push`. CI có job Playwright riêng |
| **post-merge** | `git pull` (sau merge) | Server: `VA_AUTO_BUILD_ON_PULL=1` → `npm install --omit=dev` (nếu package đổi) + `npm run build` (nếu frontend đổi) |

Helper scripts:

| Command | Description |
|---------|-------------|
| `npm run commitlint` | Validate a message manually |
| `npm run push:e2e` | `git push` with `RUN_E2E_ON_PUSH=1` (full pre-push Playwright) |
| `npm run e2e:stop-stale` | Kill `php artisan serve` on ports 8001–8020 |
| `npm run commit:msg` | Print the diff-derived commit message (header + body) for the staged changes |
| `npm run commit` | Auto-commit workflow (`scripts/auto-commit.mjs`) — styled summary box, staged file list with `+/-` churn, then commits with the generated message |

> **Commit message generation** (`scripts/generate-commit-msg.mjs`) is **fully diff-driven**: type/scope/subject/body are inferred from the real staged diff (`name-status` + `numstat`). Scope = dominant business module (e.g. `knowledge-base`, `projects`), subject = humanized "headline" file, body = per-module file counts with `+added/-deleted` churn and a totals line. No hardcoded themes.

---

## Testing (Playwright)

Config: `playwright.config.js` — test dir `tests/e2e/`, base URL `http://127.0.0.1:8000`.

| Command | Description |
|---------|-------------|
| `npm run test:e2e` | Run E2E hành vi (project `chromium`, bỏ qua `smoke/` và `visual/`) |
| `npm run test:e2e:visual` | So sánh snapshot UI theo màn (`tests/e2e/visual/`) |
| `npm run test:e2e:visual:update` | Cập nhật baseline snapshot sau khi đổi UI có chủ đích |
| `npm run test:e2e:smoke` | Chụp full-page thủ công (không so snapshot CI) |
| `npm run test:e2e:ui` | Interactive UI mode |
| `npm run test:e2e:install` | Install Chromium + OS deps (`playwright install --with-deps chromium`) |
| `npx playwright test --debug` | Debug mode (step through tests) |
| `npx playwright test tests/e2e/auth.spec.js` | Run a single spec file |
| `npx playwright test --grep "login"` | Run tests matching name |
| `npx playwright test --headed` | Visible browser window |
| `npx playwright codegen http://127.0.0.1:8000` | Record a new test |
| `npx playwright show-report` | Open HTML report from last run |

**Note:** Playwright starts `php artisan serve --host=127.0.0.1 --port=8000` automatically via `webServer` config (reuses existing server locally).

---

## Backend tests (PHPUnit)

| Command | Description |
|---------|-------------|
| `composer test` | Alias for `php artisan test` |
| `php artisan test` | Run PHPUnit test suite |
| `php artisan test --filter=TestName` | Run a single test class/method |

---

## GitHub Actions (local equivalents)

CI workflow: `.github/workflows/ci.yml` — triggered on push/PR to `main`, `master`, `develop`, or manual dispatch.

| CI job | Local equivalent |
|--------|------------------|
| PHPUnit | `composer test` |
| Frontend build | `npm ci && npm run build` |
| Playwright E2E | `npm run test:e2e:install && npm run test:e2e` |
| Laravel Pint | `composer format:test` |
| PHPStan | `composer analyse` |

Re-run failed workflow: GitHub → Actions → select run → **Re-run failed jobs**.

Skip CI on push: include `[skip ci]` in the commit message (GitHub Actions convention).

---

## Laravel artisan (common)

```bash
php artisan make:model ModelName -mcr    # Model + migration + controller + resource routes
php artisan make:controller Name --resource
php artisan route:list
php artisan config:cache
php artisan cache:clear
php artisan queue:work
php artisan key:generate                 # First-time .env setup
php artisan telegram:list-chats          # Sau khi gửi tin trong group Telegram — lấy chat_id
php artisan migrate:fresh --force --seed # CI / clean DB reset
```

### Lệnh theo lịch (scheduler)

```bash
php artisan ai-accounts:send-reminders   # Nhắc hết hạn / chưa thanh toán tài khoản AI (08:00, 14:00)
php artisan contracts:send-reminders     # CLM: đồng bộ trạng thái + cảnh báo hợp đồng sắp/đã hết hạn (08:00)
```

> Ngưỡng cảnh báo hợp đồng cấu hình ở `/settings` → tab **Hợp đồng (CLM)** (`clm.renewal_alert_days`, mặc định `90,60,30,7`). Chạy tay để kiểm thử; idempotent trong ngày.

---

## OCR service (Số hóa Phiếu Đề Xuất)

```bash
cd ocr-service
python -m venv .venv && .venv\Scripts\activate   # Windows; Linux/macOS: source .venv/bin/activate
pip install -r requirements.txt
set GEMINI_API_KEY=...                            # Linux/macOS: export
set OCR_SERVICE_TOKEN=local-secret
uvicorn app.main:app --port 8100                  # http://127.0.0.1:8100/healthz
```

Laravel `.env`: `PROPOSAL_OCR_URL=http://127.0.0.1:8100`, `PROPOSAL_OCR_TOKEN=local-secret`. Docker + API chi tiết: `ocr-service/README.md`. PHPUnit không cần service (Http::fake).

---

## Dependency install

```bash
composer install          # PHP dependencies
npm install               # Node dependencies (local dev)
npm ci                    # Clean install (CI / after lockfile change)
```

After `git pull`, if `package.json` changed, Husky `post-merge` runs `npm install` automatically.
