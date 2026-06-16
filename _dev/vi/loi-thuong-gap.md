# Lỗi thường gặp & cách xử lý

**File gốc:** [`../troubleshooting.md`](../troubleshooting.md)

Mục lục nhanh: [Husky](#husky) · [commitlint](#commitlint) · [Push/Sync](#pushsync) · [Playwright](#playwright) · [ESLint](#eslint) · [CI](#ci) · [Deploy server](#deploy-server) · [Media 404](#media-404) · [Vite/npm](#vite--npm) · [DB test](#db-test) · [AI orphan](#ai-orphan) · [Route 404 deploy](#route-404-sau-deploy) · [Realtime bình luận](#realtime-binh-luan)

---

## Deploy server {#deploy-server}

### EBADENGINE `lint-staged`

Repo pin `lint-staged@16` (Node cũ hơn 22). `git pull` bản mới → trên server:

```bash
npm ci --omit=dev
```

### `Already up to date` nhưng thiếu code

Remote chưa có commit — máy dev cần `push`. Trên server: `git fetch && git log HEAD..origin/main`.

### Tự `npm run build` sau `git pull`

```bash
cd /path/to/repo
git config core.hooksPath .husky
chmod +x .husky/post-merge scripts/post-merge-deploy.sh
echo 'export VA_AUTO_BUILD_ON_PULL=1' >> ~/.bashrc
source ~/.bashrc
```

### Deploy thủ công đầy đủ

```bash
export CI=true HUSKY=0 NODE_ENV=production
git pull
composer install --no-dev --optimize-autoloader
npm ci --omit=dev
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Không chạy `npm audit fix --force` trên production.

---

## Husky hook không chạy {#husky}

**Triệu chứng:** Commit không qua ESLint.

```bash
npm run prepare
ls .husky/   # pre-commit, commit-msg, pre-push, post-merge, prepare-commit-msg
```

**Windows:** Dùng Git Bash hoặc đảm bảo `core.hooksPath=.husky`. `chmod +x .husky/*` trên Unix/WSL.

---

## commitlint {#commitlint}

Format: `type(scope): mo ta`

```bash
echo "feat: test" | npm run commitlint
```

IDE «Updates» / trống → hook sinh message từ staged (`git add` trước) hoặc `npm run commit`.

Bỏ qua (khẩn cấp): `git commit --no-verify`

---

## Push / Sync {#pushsync}

| Tình huống | Cách xử lý |
|------------|------------|
| Sync chậm 2–3 phút (cũ) | Hiện **mặc định không E2E** khi push — nhanh |
| Muốn E2E trước push | PowerShell: `$env:RUN_E2E_ON_PUSH="1"; git push` hoặc `npm run push:e2e` |
| Cổng 8001 kẹt (khi bật E2E push) | `node tests/e2e/helpers/stopStaleE2ePorts.js` |
| Khẩn cấp | `git push --no-verify` |

Test local không push: `npm run test:e2e`

---

## Playwright {#playwright}

### Không tìm thấy Chromium

```bash
npm run test:e2e:install
```

### Windows — «The system cannot find the path specified»

1. `npm install`
2. `npm run test:e2e:install`
3. `php -v` trong cùng terminal
4. Script gọi `node node_modules/@playwright/test/cli.js` — không cần `playwright` global

### Fail local — checklist

1. Port 8000 — tắt server trùng hoặc `PLAYWRIGHT_REUSE_SERVER=1`
2. `php artisan migrate:fresh --seed`
3. Có thể cần `npm run build`
4. `npx playwright test --headed --debug`
5. `npx playwright show-report`

### POST `/login` 419 (CSRF)

Dùng `tests/e2e/helpers/loginPost.js`. Config E2E ép `SESSION_DRIVER=file`. Local `.env` dùng `redis` mà không có Redis → đổi `file` hoặc bật Redis.

---

## ESLint chặn commit {#eslint}

```bash
npm run lint:fix
git add -u
```

Warning cũng fail (`--max-warnings=0`). Khẩn cấp: `--no-verify`.

---

## pre-push E2E (khi bật RUN_E2E_ON_PUSH) {#pre-push}

Mặc định **tắt**. Nếu bật mà fail → `npm run test:e2e` local trước.

---

## CI pipeline fail {#ci}

1. GitHub → Actions → CI
2. Job đỏ: `backend-tests` | `frontend-build` | `playwright` | `static-analysis`
3. Re-run failed jobs · tải artifact Playwright

| Job | Thường do |
|-----|-----------|
| backend-tests | Pint → `vendor/bin/pint`; PHPUnit assertion / migration |
| frontend-build | ESLint → `npm run lint:fix`; lỗi Vite import |
| playwright | Selector/UI đổi; seed; snapshot visual lỗi thời |
| static-analysis | PHPStan — không chặn merge |

Trước push: skill **ship-ready**. Skip CI: `[skip ci]` trong message.

---

## npm install / build (Windows) {#vite--npm}

**`npm run build` — path not found (cmd.exe):**

1. `npm ci` hoặc xóa `node_modules` + `npm install`
2. `node -v` (Node 20 LTS)
3. Không copy folder project mà không cài lại `node_modules`

**Sau git pull lỗi dependency:**

```powershell
Remove-Item -Recurse -Force node_modules
npm install
```

---

## Composer / PHP

```bash
composer install
composer dump-autoload
cp .env.example .env && php artisan key:generate
php artisan config:clear && php artisan cache:clear && php artisan migrate
```

---

## Vite dev {#vite}

Trang trắng / `@vite/client` 404 → chạy `npm run dev` hoặc `npm run build`. Restart Vite. Alias `@/` = `resources/js/`.

---

## Media / attachment 404 {#media-404}

**Triệu chứng:** `/storage/projects/...` 404, preview «Không tải được file».

1. `php artisan storage:link`
2. File mất trên disk nhưng còn DB — upload lại hoặc xóa orphan
3. App trả `url: null` khi file mất; download qua route có auth (`projects.attachments.file`, KB tương tự)

```bash
ls storage/app/public/projects/2/customer/
```

---

## DB / SQLite test {#db-test}

```bash
touch database/testing.sqlite
php artisan migrate:fresh --seed
```

CI tạo sqlite trong workspace tự động.

---

## AI — badge «1 TK», chi phí 0 {#ai-orphan}

TK mồ côi sau PĐX — deploy code `purgeOrphanedFromProposal`, F5. Chi tiết: [docs/AI_ACCOUNTS.md](../../docs/AI_ACCOUNTS.md).

```bash
php artisan test tests/Feature/AiAccountOrphanPurgeTest.php
```

---

## Route 404 sau deploy {#route-404-sau-deploy}

**Nguyên nhân:** `bootstrap/cache/routes-*.php` cũ — route mới không có trong cache.

```bash
php artisan route:clear
php artisan route:cache
```

Deploy đúng: `optimize:clear` trước khi cache lại — [quy-trinh.md](quy-trinh.md).

---

## Realtime bình luận {#realtime-binh-luan}

User A gửi, B không thấy (không badge **Realtime**): stack thiếu Redis / Node / proxy `/socket.io`.

Checklist đầy đủ: [realtime.md](realtime.md) · [`../realtime.md`](../realtime.md).

```bash
redis-cli ping
php artisan tinker --execute="echo config('realtime.enabled') ? 'on' : 'off';"
```

Người gửi vẫn thấy tin qua Inertia partial reload; người khác cần realtime hoặc F5.
