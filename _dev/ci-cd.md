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

---

## Jobs (blocking vs advisory)

### 1. PHPUnit + Pint (`backend-tests`) — **blocking**

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

### 3. Playwright E2E (`playwright`) — **blocking**

**Needs:** `backend-tests`, `frontend-build`

| Step | Notes |
|------|--------|
| Build | `npm run build` |
| DB | `database/testing.sqlite`, `migrate:fresh --seed` |
| Run | `npm run test:e2e` with `CI=true` |

**Env:** `PLAYWRIGHT_BASE_URL`, `DB_CONNECTION=sqlite`, `DB_DATABASE` = workspace sqlite file.

**Artifacts on failure:** `playwright-report/`, `test-results/` (7 days)

**Config:** `playwright.config.js` — `workers: 1`, `reuseExistingServer` only if `PLAYWRIGHT_REUSE_SERVER=1`.

---

### 4. PHPStan (`static-analysis`) — **advisory**

`continue-on-error: true` — visible on PR, does not block merge.

---

## Job dependency graph

```
backend-tests (Pint + PHPUnit) ──┐
                                 ├──► playwright (E2E)
frontend-build (ESLint + build) ─┘

static-analysis (parallel, advisory)
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
| E2E | Local tùy chọn (`npm run push:e2e`) | Job `playwright` `CI=true` (blocking) |

---

## Skip CI

Commit message: `[skip ci]` or `[ci skip]` (GitHub convention). Does not skip Husky unless `--no-verify`.

---

## Related docs

- `_dev/testing.md` — PHPUnit, Playwright, visual project
- `_dev/troubleshooting.md` — Vite 500, storage 404, Playwright browsers
