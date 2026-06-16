# Quy trình làm việc — hướng dẫn tiếng Việt

**File gốc:** [`../workflows.md`](../workflows.md)

Dev hàng ngày · feature · PR · deploy · hotfix.

---

## Phát triển hàng ngày

1. `git pull origin main` (hoặc nhánh base)
2. `npm install && composer install` — chỉ khi lockfile đổi (`post-merge` có thể tự `npm install`)
3. `php artisan migrate` — nếu có migration mới
4. Terminal 1: `npm run dev` · Terminal 2: `php artisan serve`
5. Mở `http://127.0.0.1:8000`

**Trước commit / PR** (mirror CI — skill [ship-ready](../../.cursor/skills/ship-ready/SKILL.md)):

```bash
vendor/bin/pint --test   # khi đổi PHP
php artisan test
npm run lint
npm run build
npm run test:e2e         # khuyến nghị trước PR; không mặc định mỗi push
```

---

## Tạo tính năng mới

1. `git checkout -b feat/mo-ta-ngan`
2. Backend (FormRequest, Policy, Controller) và/hoặc frontend (Page, component, composable)
3. **Đồng bộ tài liệu** — [docs-sync](../../.cursor/rules/docs-sync.mdc):
   - Route → `docs/API_STRUCTURE.md`
   - Migration → `docs/DATABASE_STRUCTURE.md`
   - UI module → `docs/FRONTEND_STRUCTURE.md` + doc chuyên đề (KB, Coaching, …)
   - Luồng mới → `docs/FLOWS_AND_DOCS_MAP.md` hoặc doc module
4. Đổi UI → cập nhật/thêm spec `tests/e2e/`
5. `git add -p` → commit:
   - pre-commit: ESLint staged
   - prepare-commit-msg / fix-commit-msg: gợi ý message (cần `git add` trước)
   - commit-msg: commitlint
   - Hoặc: `npm run commit`
6. `git push` — **pre-push mặc định không E2E**; tùy chọn `npm run push:e2e`
7. Mở PR → CI ([ci-cd.md](ci-cd.md))

---

## Pull Request

| Hạng mục | Quy tắc |
|----------|---------|
| Tiêu đề | Conventional Commits (giống commitlint) |
| Nhánh đích | `main`, `master`, hoặc `develop` |
| Merge | Squash merge (ưu tiên) |

**CI bắt buộc pass** (`.github/workflows/ci.yml`):

| Job | Nội dung |
|-----|----------|
| `backend-tests` | `vendor/bin/pint --test` + `php artisan test` |
| `frontend-build` | `npm run lint` + `npm run build` |
| `playwright` | `npm run test:e2e` (sau 2 job trên) |

**Advisory:** `static-analysis` (PHPStan) — không chặn merge.

**Local:** ESLint pre-commit + có thể `npm run lint` tay; E2E tùy chọn trước push.

---

## Deploy (staging / production)

Repo **không** có workflow deploy — chỉ CI validate.

1. Merge vào `main` sau CI xanh
2. Trên server (SSH / panel):

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link    # nếu chưa link
php artisan optimize:clear  # BẮT BUỘC trước cache lại
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Route 404 sau deploy** (action mới, code đã có): cache route cũ — `php artisan route:clear` hoặc `optimize:clear`, rồi `route:cache` lại. **Không** chỉ chạy `config:cache`.

**Tự build sau pull (server):** `git config core.hooksPath .husky`, `VA_AUTO_BUILD_ON_PULL=1` — chi tiết [loi-thuong-gap.md](loi-thuong-gap.md).

**Realtime production:** [realtime.md](realtime.md) + [`../realtime.md`](../realtime.md).

GitLab: `.gitlab-ci.yml` mirror test — cũng không deploy.

---

## Hotfix

1. `git checkout -b fix/mo-ta-loi main`
2. Sửa tối thiểu + test regression nếu có
3. Local: `composer test`, `npm run lint`, `npm run test:e2e`
4. `git commit -m "fix(scope): mo ta"`
5. PR vào `main` — ưu tiên review
6. Deploy thủ công

---

## Script commit tự động (tùy chọn)

| Script | Lệnh |
|--------|------|
| `prepare-commit-msg.mjs` | Hook Husky |
| `generate-commit-msg.mjs` | `npm run commit:msg` |
| `auto-commit.mjs` | `npm run commit` |

Commit thủ công `-m` vẫn dùng bình thường.
