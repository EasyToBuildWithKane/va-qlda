# CI/CD — GitHub Actions

Single workflow file: `.github/workflows/ci.yml`  
GitLab mirror: `.gitlab-ci.yml` (same stages, different runner syntax).

---

## Workflow: CI

| Property | Value |
|----------|-------|
| **File** | `.github/workflows/ci.yml` |
| **Name** | `CI` |

### Triggers

| Event | Branches |
|-------|----------|
| `push` | `main`, `master`, `develop` |
| `pull_request` | → `main`, `master`, `develop` |
| `workflow_dispatch` | Manual run from GitHub Actions UI |

### Concurrency

```yaml
group: ci-${{ github.workflow }}-${{ github.ref }}
cancel-in-progress: true
```

New pushes to the same branch cancel in-progress runs.

### Global environment variables

| Variable | Value |
|----------|-------|
| `APP_ENV` | `testing` |
| `APP_DEBUG` | `true` |
| `APP_URL` | `http://127.0.0.1:8000` |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `${{ github.workspace }}/database/testing.sqlite` |

No secrets are required for CI — uses SQLite file database and `.env.example`.

---

## Jobs

### 1. PHPUnit (`backend-tests`)

| Property | Value |
|----------|-------|
| **Runs on** | `ubuntu-latest` |
| **PHP** | 8.2 (dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite) |
| **Blocking** | Yes |

**Steps:**

1. Checkout code
2. Setup PHP 8.2 with Composer cache
3. `composer install --no-interaction --prefer-dist --optimize-autoloader`
4. `cp .env.example .env` + `php artisan key:generate`
5. `php artisan test`

---

### 2. Frontend build (`frontend-build`)

| Property | Value |
|----------|-------|
| **Runs on** | `ubuntu-latest` |
| **Node** | 20 (npm cache enabled) |
| **Blocking** | Yes |

**Steps:**

1. Checkout code
2. `npm ci`
3. `npm run build` (Vite production build)

---

### 3. Playwright E2E (`playwright`)

| Property | Value |
|----------|-------|
| **Runs on** | `ubuntu-latest` |
| **Needs** | `backend-tests`, `frontend-build` (runs only after both pass) |
| **Blocking** | Yes |

**Steps:**

1. Checkout + setup PHP 8.2 + Node 20
2. `composer install` + `npm ci`
3. `npm run build`
4. Prepare Laravel:
   - `cp .env.example .env`
   - `php artisan key:generate`
   - Create `database/testing.sqlite`
   - `php artisan migrate:fresh --force --seed`
5. `npx playwright install --with-deps chromium`
6. `npm run test:e2e`

**Job-specific env:**

| Variable | Value |
|----------|-------|
| `PLAYWRIGHT_BASE_URL` | `http://127.0.0.1:8000` |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `${{ github.workspace }}/database/testing.sqlite` |

**Artifacts on failure:**

- Name: `playwright-report`
- Paths: `playwright-report/`, `test-results/`
- Retention: 7 days

Playwright config auto-starts `php artisan serve` via `webServer` — CI sets `reuseExistingServer: false`.

---

### 4. Laravel Pint (`code-style`)

| Property | Value |
|----------|-------|
| **Runs on** | `ubuntu-latest` |
| **Blocking** | **No** (`continue-on-error: true`) |

**Steps:**

1. Checkout + PHP 8.2
2. `composer install`
3. `vendor/bin/pint --test` (dry-run style check)

---

### 5. PHPStan (`static-analysis`)

| Property | Value |
|----------|-------|
| **Runs on** | `ubuntu-latest` |
| **Blocking** | **No** (`continue-on-error: true`) |

**Steps:**

1. Checkout + PHP 8.2
2. `composer install`
3. `vendor/bin/phpstan analyse --no-progress --memory-limit=512M`

---

## Job dependency graph

```
backend-tests ──┐
                ├──► playwright
frontend-build ─┘

code-style      (parallel, independent)
static-analysis (parallel, independent)
```

---

## Deployment

**No deploy job or workflow exists.** CI validates code only.

Deployment targets (staging/production) are handled outside this repository's GitHub Actions config.

---

## How to re-run a failed workflow

1. Go to **GitHub → Actions → CI**
2. Click the failed run
3. Click **Re-run failed jobs** (or **Re-run all jobs**)
4. For Playwright failures: download the `playwright-report` artifact from the failed run

---

## How to skip CI

Add one of these to your commit message (GitHub Actions convention):

```
chore: update docs [skip ci]
fix: typo [ci skip]
```

Or use `workflow_dispatch` to run CI manually when needed.

**Note:** Skipping CI does not bypass local Husky hooks — pre-commit ESLint and pre-push Playwright still run unless you use `git commit --no-verify` / `git push --no-verify`.

---

## Local vs CI differences

| Aspect | Local (dev) | CI |
|--------|-------------|-----|
| Playwright retries | 0 | 2 |
| Playwright workers | auto | 1 |
| Playwright reporter | list + html | github + html |
| pre-push E2E | Runs (unless `CI=true`) | Skipped (CI runs its own job) |
| ESLint | pre-commit hook | Not in CI pipeline |
| Database | Your `.env` | SQLite `database/testing.sqlite` |
