# Project memory — quick reference

VA-QLDA (VAschools Quản lý Dự Án) — Laravel 10 + Vue 3 + Inertia.

---

## Stack

- **Backend:** Laravel 10 (PHP 8.1+), MySQL (`va_prd_` table prefix), PHPUnit
- **Frontend:** Vue 3 + Vite + Inertia (`resources/js/`), Tailwind CSS
- **Quality:** ESLint 9, Husky, commitlint, Laravel Pint, PHPStan
- **E2E:** Playwright (Chromium)
- **CI:** GitHub Actions (`.github/workflows/ci.yml`), GitLab mirror (`.gitlab-ci.yml`)
- **Tooling:** Cursor (`.cursor/`), Claude (`CLAUDE.md`), Husky (`.husky/`)

---

## Quick commands (copy-paste)

```bash
npm run dev                              # Vite dev server
php artisan serve                        # Laravel backend :8000
npm run lint                             # ESLint (zero warnings)
npm run build                            # Production assets
composer test                            # PHPUnit
npm run test:e2e                         # Playwright E2E
php artisan migrate                      # Run migrations
php artisan migrate:fresh --seed         # Reset DB + seed
npm run prepare                          # Reinstall Husky hooks
npx playwright show-report               # View last E2E report
```

---

## Workflow cheatsheet

| Workflow | One-liner |
|----------|-----------|
| **Daily dev** | `git pull` → `npm install`/`composer install` if needed → `migrate` → `npm run dev` + `php artisan serve` |
| **New feature** | `git checkout -b feat/name` → code → **sync `docs/`** (`.cursor/rules/docs-sync.mdc`) → E2E test → commit → push → PR |
| **Pull request** | Conventional Commits title · CI must pass (PHPUnit + build + Playwright) · squash merge |
| **Deploy** | No automated deploy workflow — merge to `main`, deploy manually to staging/prod |
| **Hotfix** | `git checkout -b fix/name main` → fix + test → commit → PR to `main` → manual deploy |

---

## File index

| File | Covers |
|------|--------|
| [commands.md](commands.md) | All CLI commands — npm, artisan, Husky, Playwright, Composer |
| [workflows.md](workflows.md) | Daily dev, feature branches, PR process, deploy, hotfix |
| [conventions.md](conventions.md) | Commits, branches, Vue/PHP style, ESLint rules |
| [ci-cd.md](ci-cd.md) | GitHub Actions jobs, env vars, re-run, skip CI |
| [testing.md](testing.md) | Playwright setup, config, running tests, CI integration |
| [realtime.md](realtime.md) | Socket.IO realtime comments — Node server, Redis bridge, thread token |
| [troubleshooting.md](troubleshooting.md) | Husky, commitlint, Playwright, ESLint, CI, npm fixes |

### Vietnamese (`vi/`)

| File | Covers |
|------|--------|
| [vi/README.md](vi/README.md) | Mục lục VI, lộ trình đọc, link `docs/` |
| [vi/tong-quan.md](vi/tong-quan.md) | Tổng quan `_dev/`, lệnh hay dùng |
| [vi/lenh-cli.md](vi/lenh-cli.md) | Giải thích [`commands.md`](commands.md) |
| [vi/quy-trinh.md](vi/quy-trinh.md) | Giải thích [`workflows.md`](workflows.md) |
| [vi/quy-uoc.md](vi/quy-uoc.md) | Giải thích [`conventions.md`](conventions.md) |
| [vi/ci-cd.md](vi/ci-cd.md) | Giải thích [`ci-cd.md`](ci-cd.md) |
| [vi/kiem-thu.md](vi/kiem-thu.md) | Giải thích [`testing.md`](testing.md) |
| [vi/realtime.md](vi/realtime.md) | Giải thích [`realtime.md`](realtime.md) |
| [vi/loi-thuong-gap.md](vi/loi-thuong-gap.md) | Giải thích [`troubleshooting.md`](troubleshooting.md) |

**Convention:** `_dev/*.md` (EN) = canonical · `_dev/vi/*.md` = Vietnamese explanations (link back to EN).

---

## Technical docs (`docs/`)

Spec module, route map, schema, **sơ đồ luồng**: [`docs/FLOWS_AND_DOCS_MAP.md`](../docs/FLOWS_AND_DOCS_MAP.md) (hub) · index đầy đủ trong file đó §10.  
`_dev/` không thay thế `docs/` cho thiết kế feature — xem `.cursor/rules/docs-sync.mdc`.

---

## Key config files

| Path | Purpose |
|------|---------|
| `package.json` | npm scripts, lint-staged, devDependencies |
| `vite.config.js` | Vite + Laravel plugin, `@/` alias |
| `eslint.config.js` | ESLint 9 flat config |
| `commitlint.config.js` | Conventional Commits rules |
| `playwright.config.js` | E2E test config + webServer |
| `.husky/*` | Git hooks (lint, commitlint, E2E, post-merge) |
| `.github/workflows/ci.yml` | CI pipeline |
| `composer.json` | PHP deps, Pint, PHPStan scripts |
