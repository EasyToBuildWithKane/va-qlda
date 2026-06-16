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

Quality checks before committing (mirror CI — see `ci-cd.md`):

```bash
vendor/bin/pint --test   # blocking on CI
php artisan test
npm run lint               # blocking on CI
npm run build
npm run test:e2e           # trước PR / khi cần; không chạy mặc định khi push
```

Skill: `.cursor/skills/ship-ready/SKILL.md`.

---

## Creating a feature

1. `git checkout -b feat/short-description`
2. Implement backend (Controller, FormRequest, Policy) and/or frontend (Page, Component, composable)
3. **Cập nhật tài liệu** — map trong `.cursor/rules/docs-sync.mdc` (vd. route → `docs/API_STRUCTURE.md`, migration → `docs/DATABASE_STRUCTURE.md`, module → doc chuyên đề)
4. If UI changes: add/update Playwright spec in `tests/e2e/`
5. `git add -p` — stage selectively
5. `npm run commit` hoặc `git commit` (message IDE «Updates» → hook tự sửa nếu đã `git add`)
   - **pre-commit** → ESLint staged Vue/JS
   - **prepare-commit-msg** / **fix-commit-msg** → gợi ý Conventional Commits
   - **commit-msg** → commitlint
6. `git push` / Sync — **pre-push mặc định bỏ qua E2E** (nhanh). Tùy chọn: `npm run push:e2e`
7. Open PR on GitHub → CI runs automatically (see `ci-cd.md`)

---

## Pull request process

- **Title** must follow [Conventional Commits](https://www.conventionalcommits.org/) — same rules as commitlint
- **Target branch:** `main`, `master`, or `develop`
- **Required CI checks** (`.github/workflows/ci.yml`):
  - **PHPUnit + Pint** (`vendor/bin/pint --test`, then `php artisan test`)
  - **Frontend** (`npm run lint`, then `npm run build`)
  - **Playwright E2E** (after both pass; `CI=true`, single worker)
- **Advisory:** PHPStan (`continue-on-error: true`)
- **Local:** pre-commit ESLint; E2E khi `npm run test:e2e` hoặc `npm run push:e2e`
- **Merge strategy:** squash merge preferred for clean history
- **Review:** push fix → CI chạy E2E (không bắt buộc E2E local mỗi lần Sync)

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
3. On server:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan migrate --force
   php artisan optimize:clear   # IMPORTANT: drop stale config/route/view caches first
   php artisan config:cache
   php artisan route:cache      # without this, newly added routes 404 on a server with a cached route table
   php artisan view:cache
   ```
   > **Route 404 after deploy?** A stale `bootstrap/cache/routes-*.php` keeps serving the old route table, so any route added since the last cache returns 404 (e.g. `POST /projects/{project}/tasks/import`). Fix immediately with `php artisan route:clear` (or `optimize:clear`), then re-run `route:cache`. Never run only `config:cache` — it does not refresh routes.

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

## Credential Management (vault)

1. `php artisan migrate` — bảng `credentials` và liên quan.
2. Sidebar **Bảo mật & Tài sản Số** → **Tài khoản & Mật khẩu** (`/credentials`).
3. **Thêm tài khoản:** `/credentials/create` hoặc toolbar; mật khẩu lưu cast `encrypted`.
4. **Xem mật khẩu:** tab Bảo mật → Hiện/Sao chép (API `api.credentials.show-password`, có audit + throttle 30/phút).
5. **Phân quyền:** tab Phân quyền → cấp grant hoặc duyệt **yêu cầu truy cập** (owner/admin).
6. **Nhập hàng loạt:** Index → **Dữ liệu** → file mẫu `VA_CREDENTIAL_IMPORT_V1`, tối đa 200 dòng → `POST /credentials/import`.
7. Doc: `docs/CREDENTIAL_MANAGEMENT.md`, route map trong `docs/API_STRUCTURE.md`.

---

## Auto-commit helpers

This project includes optional commit automation scripts:

| Script | Command | Purpose |
|--------|---------|---------|
| `scripts/prepare-commit-msg.mjs` | *(Husky hook)* | Pre-fill commit message from staged diff |
| `scripts/generate-commit-msg.mjs` | `npm run commit:msg` | Print suggested Conventional Commit |
| `scripts/auto-commit.mjs` | `npm run commit` | Stage + commit with generated message |

Use these when you want AI-assisted commit messages; manual commits with `-m` still work normally.
