# Workflows — VA-QLDA

Step-by-step guides for day-to-day development, PRs, deploy, and hotfixes.

---

## Daily development

1. `git pull origin main` (or your base branch)
2. `npm install && composer install` — only if `package.json` / `composer.lock` changed  
   *(Husky `post-merge` auto-runs `npm install` when `package.json` changes)*
3. `php artisan migrate` — if new migrations were pulled
4. Start two terminals:
   - Terminal 1: `npm run dev` (Vite HMR)
   - Terminal 2: `php artisan serve` (Laravel on port 8000)
5. Open `http://127.0.0.1:8000`

Optional quality checks before committing:

```bash
npm run lint
composer test
npm run test:e2e        # also runs automatically on git push
```

---

## Creating a feature

1. `git checkout -b feat/short-description`
2. Implement backend (Controller, FormRequest, Policy) and/or frontend (Page, Component, composable)
3. If UI changes: add/update Playwright spec in `tests/e2e/`
4. `git add -p` — stage selectively
5. `git commit -m "feat(scope): description"`
   - **pre-commit** → ESLint on staged Vue/JS via lint-staged
   - **commit-msg** → commitlint validates format
   - *(optional)* **prepare-commit-msg** may auto-suggest a message from staged diff
6. `git push origin feat/short-description`
   - **pre-push** → full Playwright E2E suite (skipped when `CI=true`)
7. Open PR on GitHub → CI runs automatically (see `ci-cd.md`)

---

## Pull request process

- **Title** must follow [Conventional Commits](https://www.conventionalcommits.org/) — same rules as commitlint
- **Target branch:** `main`, `master`, or `develop`
- **Required CI checks** (`.github/workflows/ci.yml`):
  - PHPUnit (`php artisan test`)
  - Frontend build (`npm run build`)
  - Playwright E2E (runs after PHPUnit + build pass)
- **Advisory checks** (`continue-on-error: true` — visible but do not block merge):
  - Laravel Pint (PHP code style)
  - PHPStan (static analysis)
- **ESLint** is enforced locally via Husky pre-commit, not as a separate CI job
- **Merge strategy:** squash merge preferred for clean history
- **Review:** address feedback, push fixes — pre-push hook re-runs E2E

---

## Deploy (staging / production)

**No deploy workflow exists** in `.github/workflows/` — only the `CI` workflow is configured.

Current CI pipeline validates code quality; deployment is **manual or external** (not automated in this repo).

If you add deploy later, typical pattern:

```yaml
# Example — not present today
on:
  push:
    branches: [main]
    tags: ['v*']
```

**GitLab mirror:** `.gitlab-ci.yml` mirrors the same test/build/e2e/quality stages for GitLab runners — also no deploy stage.

Until a deploy workflow is added:

1. Merge PR to `main` after CI passes
2. Deploy via your hosting pipeline (ServBay, Forge, manual `git pull` on server, etc.)
3. On server: `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan config:cache`

---

## Hotfix

1. `git checkout -b fix/issue-description main`
2. Apply minimal fix + add regression test if applicable
3. Verify locally:
   ```bash
   composer test
   npm run lint
   npm run test:e2e
   ```
4. `git commit -m "fix(scope): description"`
5. `git push origin fix/issue-description`
6. Open PR targeting `main` — prioritize review and merge
7. Deploy fix to production per your manual deploy process

---

## Auto-commit helpers

This project includes optional commit automation scripts:

| Script | Command | Purpose |
|--------|---------|---------|
| `scripts/prepare-commit-msg.mjs` | *(Husky hook)* | Pre-fill commit message from staged diff |
| `scripts/generate-commit-msg.mjs` | `npm run commit:msg` | Print suggested Conventional Commit |
| `scripts/auto-commit.mjs` | `npm run commit` | Stage + commit with generated message |

Use these when you want AI-assisted commit messages; manual commits with `-m` still work normally.
