# CI/CD — GitHub Actions

Single workflow file: `.github/workflows/ci.yml`  
GitLab mirror: `.gitlab-ci.yml` (same stages, different runner syntax).

Agent/user **before push:** `.cursor/skills/ship-ready/SKILL.md` · rule `.cursor/rules/ci-quality-gates.mdc`

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

### Global environment variables

| Variable | Value |
|----------|-------|
| `APP_ENV` | `testing` |
| `APP_DEBUG` | `true` |
| `APP_URL` | `http://127.0.0.1:8000` |

SQLite for E2E is set **only** on the Playwright job (`DB_CONNECTION`, `DB_DATABASE`). PHPUnit uses in-memory/sqlite from `.env.example` + `tests/TestCase` Vite stub (no `npm build` required in PHPUnit job).

### Path filtering (`detect-changes`)

First job runs [`dorny/paths-filter`](https://github.com/dorny/paths-filter) and exposes outputs `backend`, `frontend`, `code`. Downstream jobs are gated with `if:` so a **docs-only PR skips PHPUnit/PHPStan/build/E2E** entirely.

| Filter | Globs |
|--------|-------|
| `backend` | `app/** routes/** database/** config/** bootstrap/** tests/** composer.* phpstan.neon.dist artisan .env.example` |
| `frontend` | `resources/** package*.json vite/tailwind/postcss/eslint config` |
| `code` | `backend OR frontend` |

---

## Jobs (blocking vs advisory)

### 0. Detect changes (`detect-changes`)

Computes path-filter outputs consumed by every other job. Always runs.

### 0b. Commitlint (`commitlint`) — **blocking, PR only**

`npx commitlint --from <base> --to <head> --verbose` validates **every commit in the PR** against `commitlint.config.js`. Runs only on `pull_request` (needs `fetch-depth: 0`).

### 1. PHPUnit + Pint (`backend-tests`) — **blocking** · _if `backend` changed_

| Step | Command |
|------|---------|
| Prepare | `cp .env.example .env`, `php artisan key:generate` |
| Style | `vendor/bin/pint --test` |
| Tests | `php artisan test` |

`tests/TestCase.php` creates a minimal `public/build/manifest.json` when missing so Inertia `@vite` does not return 500 without a frontend build on this job.

---

### 2. Frontend build + ESLint (`frontend-build`) — **blocking**

| Step | Command |
|------|---------|
| Install | `npm ci` |
| Lint | `npm run lint` |
| Build | `npm run build` |

---

### 2. Frontend build + ESLint (`frontend-build`) — **blocking** · _if `frontend` changed_

Uploads `public/build/` as artifact `frontend-build` (3 days) on success.

### 3. Playwright E2E (`playwright`) — **blocking** · _if any `code` changed_

**Needs:** `detect-changes`, `backend-tests`, `frontend-build` — guarded by `if: always() && …result != 'failure'` so it still runs when one prerequisite was **skipped** (e.g. frontend-only change skips `backend-tests`) but is held back when one **failed**.

| Step | Notes |
|------|--------|
| Build | `npm run build` |
| DB | `database/testing.sqlite`, `migrate:fresh --seed` |
| Run | `npm run test:e2e` with `CI=true` |

**Env:** `PLAYWRIGHT_BASE_URL`, `DB_CONNECTION=sqlite`, `DB_DATABASE` = workspace sqlite file.

**Artifacts on failure:** `playwright-report/`, `test-results/` (7 days)

**Config:** `playwright.config.js` — `workers: 1`, `reuseExistingServer` only if `PLAYWRIGHT_REUSE_SERVER=1`.

---

### 4. PHPStan (`static-analysis`) — **blocking** · _if `backend` changed_

Now **blocking** (was `continue-on-error`). Runs `phpstan analyse --memory-limit=1G --error-format=github` so findings appear as inline PR annotations. Result cache lives in `build/phpstan` (`tmpDir` in `phpstan.neon.dist`, gitignored) and is cached across runs via `actions/cache` keyed on `composer.lock` + config.

### 5. CI success (`ci-success`) — **aggregate gate**

`needs: [all jobs]`, `if: always()`. Fails if any job is `failure`/`cancelled`; **skipped jobs are accepted**. Use this single check for branch protection so path-filtered skips don't block merges.

---

## Job dependency graph

```
detect-changes (path filters)
   ├─ commitlint (PR only)
   ├─ backend-tests (Pint + PHPUnit)   ─┐
   ├─ frontend-build (ESLint + build)  ─┼─► playwright (E2E)
   └─ static-analysis (PHPStan, cached) │
                                        ▼
                  ci-success (aggregate gate, always)
```

**Removed:** separate `code-style` job (Pint merged into `backend-tests`).

---

## Deployment

**No deploy job** in this repo. CI validates only.

---

## Local vs CI

| Check | Local | CI |
|-------|-------|-----|
| Pint | Run before push PHP changes | Blocking in `backend-tests` |
| ESLint | pre-commit (staged) + `npm run lint` | Blocking in `frontend-build` |
| PHPUnit | `php artisan test` | `backend-tests` |
| PHPStan | `vendor/bin/phpstan analyse --memory-limit=1G` | `static-analysis` (blocking) |
| Commitlint | pre-commit/commit-msg hook | `commitlint` job over all PR commits |
| E2E | Local tùy chọn (`npm run push:e2e`) | Job `playwright` `CI=true` (blocking) |

---

## Skip CI

Commit message: `[skip ci]` or `[ci skip]` (GitHub convention). Does not skip Husky unless `--no-verify`.

---

## Related docs

- `_dev/testing.md` — PHPUnit, Playwright, visual project
- `_dev/troubleshooting.md` — Vite 500, storage 404, Playwright browsers
