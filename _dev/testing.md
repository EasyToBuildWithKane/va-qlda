# Testing — Playwright E2E

End-to-end tests for VA-QLDA UI flows. Config: `playwright.config.js`.

---

## Setup

First time (or after Playwright upgrade):

```bash
npm run test:e2e:install
# equivalent: npx playwright install --with-deps chromium
```

Requires Node 20+ and PHP 8.1+ with Composer dependencies installed.

---

## Config overview

| Setting | Value |
|---------|-------|
| **Test directory** | `./tests/e2e` |
| **Base URL** | `process.env.PLAYWRIGHT_BASE_URL` ?? `http://127.0.0.1:8000` |
| **Browsers** | Chromium only (`Desktop Chrome` device profile) |
| **Global setup** | `./tests/e2e/global-setup.js` |
| **Parallel** | `fullyParallel: true` |
| **Retries** | 0 local · 2 in CI |
| **Workers** | auto local · 1 in CI |

### Reporters

| Environment | Reporters |
|-------------|-----------|
| **Local** | `list` + `html` (opens on failure) |
| **CI** | `github` + `html` (never auto-open) |

### Trace / screenshots / video

| Setting | Value |
|---------|-------|
| Trace | `on-first-retry` |
| Screenshot | `only-on-failure` |
| Video | `retain-on-failure` |

### Web server (auto-started)

Playwright starts Laravel before tests:

```js
command: 'php artisan serve --host=127.0.0.1 --port=8000'
reuseExistingServer: !isCI   // true locally — reuses running server
timeout: 120_000
```

Server env includes E2E database config from `tests/e2e/helpers/database.js`.

---

## Existing specs

| File | Coverage |
|------|----------|
| `tests/e2e/auth.spec.js` | Login page render, member sign-in → dashboard, invalid credentials |

Add new specs as `tests/e2e/<feature>.spec.js`.

---

## Running tests

```bash
# All tests, headless
npm run test:e2e

# Interactive GUI
npm run test:e2e:ui

# Visible browser
npx playwright test --headed

# Single file
npx playwright test tests/e2e/auth.spec.js

# Filter by test name
npx playwright test --grep "login"

# Debug step-by-step
npx playwright test --debug

# Record new test
npx playwright codegen http://127.0.0.1:8000

# View HTML report from last run
npx playwright show-report
```

**Tip:** If you already have `php artisan serve` running on port 8000, Playwright reuses it locally (`reuseExistingServer: true`).

---

## CI integration

Workflow: `.github/workflows/ci.yml` → job `playwright`

1. Runs after `backend-tests` and `frontend-build` pass
2. Creates fresh SQLite DB: `database/testing.sqlite`
3. Runs `php artisan migrate:fresh --force --seed`
4. Installs Chromium: `npx playwright install --with-deps chromium`
5. Runs `npm run test:e2e` with `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8000`
6. On failure: uploads `playwright-report/` and `test-results/` as artifact (7-day retention)

**Local pre-push hook** also runs E2E before every `git push` (skipped when `CI=true`).

---

## Backend tests (PHPUnit)

Separate from Playwright — unit/feature tests via PHPUnit:

```bash
composer test          # php artisan test
php artisan test --filter=AuthenticationTest
```

CI job `backend-tests` runs this before Playwright.

---

## Artifacts

| Path | Contents |
|------|----------|
| `playwright-report/` | HTML test report (generated after each run) |
| `test-results/` | Screenshots, videos, traces on failure |

Both directories are gitignored. In CI, download from the **playwright-report** artifact on failed runs.

---

## Writing new tests

```js
import { test, expect } from '@playwright/test';

test.describe('Feature name', () => {
    test('does something', async ({ page }) => {
        await page.goto('/some-route');
        await expect(page.getByRole('heading', { name: 'Expected' })).toBeVisible();
    });
});
```

Use role/label selectors (`getByRole`, `getByLabel`) over CSS selectors when possible — matches existing `auth.spec.js` patterns.

Test credentials (from seeders): username `member`, password `password`.
