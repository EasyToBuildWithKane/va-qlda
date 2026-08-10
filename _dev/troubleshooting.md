# Troubleshooting — VA-Workspace

Common issues and fixes for local dev, Git hooks, and CI.

---

## Server deploy (`public_html`) — `npm warn EBADENGINE` / `git pull` up to date

**EBADENGINE `lint-staged`:** Bản cũ yêu cầu Node ≥22.22.1. Repo đã pin `lint-staged@16` — `git pull` lại rồi cài đúng cách production (bên dưới).

**`Already up to date`:** Remote chưa có commit mới — máy dev cần `git push` trước; trên server `git fetch && git log HEAD..origin/main`.

**Tự `npm run build` sau `git pull` (một lần cấu hình server):**

```bash
cd /path/to/public_html   # thư mục repo
git config core.hooksPath .husky
chmod +x .husky/post-merge scripts/post-merge-deploy.sh
echo 'export VA_AUTO_BUILD_ON_PULL=1' >> ~/.bashrc   # hoặc export trước mỗi pull
source ~/.bashrc
```

Sau đó mỗi `git pull` (có thay đổi `resources/`, `package.json`, vite/tailwind…): hook chạy `npm install --omit=dev` (nếu cần) và **`npm run build`**.

**Deploy thủ công đầy đủ (lần đầu / không dùng hook):**

```bash
export CI=true HUSKY=0 NODE_ENV=production VA_AUTO_BUILD_ON_PULL=1
git pull
composer install --no-dev --optimize-autoloader
npm ci --omit=dev
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan storage:link
```

`npm audit` trên server: thường **không** chạy `npm audit fix --force` trên production — xử lý trên môi trường dev/CI.

**Vite manifest not found** (`public/build/manifest.json`): Deploy PHP mà chưa `npm run build` — mọi trang Inertia 500. Chạy `npm ci --omit=dev && npm run build` trên server (hoặc bật hook `post-merge` ở trên).

---

## Husky hooks not running

**Symptoms:** Commits succeed without ESLint; push succeeds without E2E.

**Fix:**

```bash
npm run prepare          # installs Husky hooks → node node_modules/husky/bin.js
```

**Verify hooks exist:**

```bash
ls .husky/
# Expected: pre-commit, commit-msg, pre-push, post-merge, prepare-commit-msg
```

**Windows note:** Hooks are shell scripts. Git Bash (bundled with Git for Windows) runs them. If hooks fail silently, run Git commands from Git Bash or ensure `core.hooksPath` points to `.husky`.

**Fallback (Unix/macOS/WSL):**

```bash
chmod +x .husky/*
```

---

## commitlint failing

**Symptoms:** `git commit` rejected with commitlint errors.

**Cursor / VS Code gõ "Updates" hoặc để trống:** Hook `fix-commit-msg.mjs` + `prepare-commit-msg.mjs` tự thay bằng message từ `git diff --cached` (cần có file staged). Hoặc chạy `npm run commit` / `npm run commit:msg`.

**Required format:** `type(scope): description`

```bash
# Test a message
echo "feat: test message" | npm run commitlint
```

**Valid types:** feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert

**Rules:** header ≤ 72 chars, body lines ≤ 100 chars, no PascalCase/sentence-case subject.

**Skip once (not recommended):**

```bash
git commit --no-verify -m "feat: emergency fix"
```

**Auto-suggest message:**

```bash
npm run commit:msg       # print suggested message
npm run commit            # auto stage + commit
```

---

## Playwright browser not found

**Symptoms:** `Executable doesn't exist at .../chromium-...`

**Fix:**

```bash
npm run test:e2e:install
# or
npx playwright install chromium
npx playwright install --with-deps chromium   # includes OS libraries (CI/Linux)
```

---

## Project documents or avatars return 404

**Symptoms:** Console `Failed to load resource` for `/storage/projects/...` or bare `.jpg` names; preview shows `Không tải được file (404)`.

**Causes:**

1. **`storage:link` missing** — run `php artisan storage:link` so `public/storage` points to `storage/app/public`.
2. **DB rows without files** — attachment paths in DB but files were never copied (new machine, restore DB only). Re-upload in **Tài liệu** or delete orphan rows.
3. **Legacy avatar filename** — API now resolves full URLs only when the file exists on the public disk.

**Verify a file exists:**

```bash
# Example path from DB: projects/2/customer/abc.pdf
ls storage/app/public/projects/2/customer/
```

**App behavior:** Missing files return `url: null` and a Vietnamese message instead of spamming failed fetches; downloads use authenticated route `projects.attachments.file`.

---

## Knowledge base upload 500 (`UnableToCreateDirectory`)

**Symptoms:** Chèn ảnh / đính kèm trên `/knowledge-base/articles/create` hoặc edit → HTTP 500; log:

`Unable to create a directory at .../storage/app/public/knowledge-base/{id}/images`

**Cause:** PHP-FPM user cannot create directories under `storage/app/public` (ownership or mode). Not an application logic bug.

**Fix on server** (adjust user/group to match your vhost — often `www-data`, `nginx`, or the cPanel account user):

```bash
cd /path/to/public_html   # e.g. /home/projects.vaschools.edu.vn/public_html
mkdir -p storage/app/public/knowledge-base
chmod -R ug+rwx storage bootstrap/cache
chown -R USER:GROUP storage bootstrap/cache
php artisan storage:link   # if public/storage missing
```

Re-test upload. Same fix applies to **`blockers/{id}/`**, `projects/…`, `knowledge-base/…`, `avatars/`, v.v.

---

## Profile `/profile` 500 — `getKey() on array` (EmployeeProfileResource)

**Symptoms:** Log `Call to a member function getKey() on array` at `EmployeeProfileResource::teams()` when the user leads an org team but has no `org_team_members` row.

**Cause:** Older code merged plain arrays into an Eloquent `Collection` via `merge()`.

**Fix:** Deploy `main` (uses `collect()` + `concat()`). Regression: `ProfileSelfTest::test_show_team_leader_without_membership_row_lists_led_team`.

---

## Sync Changes chậm / lỗi (Husky pre-push)

**Sync lâu (2–3 phút):** Trước đây mỗi push chạy full Playwright. **Hiện tại mặc định không chạy E2E khi push** — Sync nhanh. CI GitHub vẫn test.

**Chạy E2E trước khi push (tùy chọn):**

```powershell
$env:RUN_E2E_ON_PUSH="1"; git push
```

**Push vẫn fail (cổng / E2E cũ):** Chỉ khi `RUN_E2E_ON_PUSH=1`. Dọn stale server:

```powershell
node tests/e2e/helpers/stopStaleE2ePorts.js
netstat -ano | findstr "LISTENING" | findstr ":800"
taskkill /F /PID <pid>
```

**Bỏ qua mọi hook push (khẩn cấp):** `git push --no-verify`

**Test local không push:** `npm run test:e2e`

---

## Playwright tests fail locally

**`The system cannot find the path specified` (Windows, ngay sau `test:e2e:visual`):**

1. Cài dependency: `npm install`
2. Cài browser: `npm run test:e2e:install`
3. Script E2E gọi trực tiếp `node node_modules/@playwright/test/cli.js` — không dùng lệnh `playwright` global.
4. `php` phải có trong PATH (chạy thử `php -v` trong cùng terminal CMD).

**Checklist:**

1. Default E2E port is **8000** (`npm run test:e2e`). Nếu đang chạy `php artisan serve` trên 8000: tắt server đó **hoặc** `$env:PLAYWRIGHT_REUSE_SERVER='1'; npm run test:e2e:visual` (PowerShell). Pre-push dùng **8001**.
2. Database migrated and seeded?
   ```bash
   php artisan migrate:fresh --seed
   ```
3. Frontend assets built (for production-like tests)?
   ```bash
   npm run build
   ```
4. Run with visible browser to debug:
   ```bash
   npx playwright test --headed --debug
   ```
5. View last report:
   ```bash
   npx playwright show-report
   ```

---

## ESLint errors blocking commit

**Symptoms:** pre-commit hook fails with ESLint errors.

**Auto-fix staged files:**

```bash
npm run lint:fix
git add -u
git commit -m "fix: resolve lint errors"
```

**Fix specific directory:**

```bash
npx eslint --fix resources/js/
```

**Skip once:**

```bash
git commit --no-verify
```

**Note:** `npm run lint` uses `--max-warnings=0` — warnings also block commits.

---

## pre-push E2E blocking push

**Symptoms:** `git push` aborted — "E2E tests failed."

**Fix:** Run tests locally first:

```bash
npm run test:e2e
```

**Skip once (emergency only):**

```bash
RUN_E2E_ON_PUSH=1 git push
# hoặc
git push --no-verify
```

**Mặc định:** pre-push **không** chạy E2E. **CI:** hook bỏ qua khi `CI=true` (runner có job E2E).

---

## CI pipeline failing

1. Open **GitHub → Actions → CI** → failed run
2. Expand the failing job (PHPUnit, Frontend build, Playwright, Pint, PHPStan)
3. Click **Re-run failed jobs**
4. For Playwright: download `playwright-report` artifact

**Common CI failures:**

| Job | Typical cause |
|-----|---------------|
| PHPUnit | Missing migration, test assertion failure, env issue |
| Frontend build | Vite build error, missing import |
| Playwright | UI change broke selector, seed data mismatch |
| Pint | PHP formatting drift (`composer format` to fix locally) |
| PHPStan | Type/static analysis violation |

**Skip CI on push:**

```bash
git commit -m "chore: docs update [skip ci]"
```

---

## `npm run build` — The system cannot find the path specified (Windows)

**Symptoms:** `npm run build` stops immediately with that message (often in **cmd.exe**), no Vite banner.

**Fix:**

1. `npm ci` (or delete `node_modules` then `npm install`).
2. Use project scripts (they call Node explicitly): `npm run build` → `node node_modules/vite/bin/vite.js build`.
3. Same terminal: `node -v` must work (Node 20 LTS).
4. Avoid running from a copied/moved folder without reinstalling `node_modules`.

---

## npm install fails after git pull

**Symptoms:** Module not found, peer dependency errors after merge.

**Fix:**

```bash
rm -rf node_modules package-lock.json   # PowerShell: Remove-Item -Recurse -Force node_modules, package-lock.json
npm install
```

Husky `post-merge` runs `npm install` when `package.json` changes — if it fails, run manually.

---

## Composer / PHP issues

```bash
composer install
php artisan config:clear
php artisan cache:clear
php artisan migrate
```

**Class not found after new files:**

```bash
composer dump-autoload
```

**`.env` missing:**

```bash
cp .env.example .env
php artisan key:generate
```

---

## Vite dev server issues

**Symptoms:** `@vite/client` 404, blank page, HMR not working.

1. Ensure `npm run dev` is running
2. Check `public/build/` is not stale — run `npm run build` if testing without dev server
3. Clear browser cache
4. Restart Vite: Ctrl+C → `npm run dev`

**Alias not resolving:** `@/` maps to `resources/js/` in `vite.config.js`.

---

## SQLite / database errors in tests

E2E uses config from `tests/e2e/helpers/database.js`. Ensure:

```bash
touch database/testing.sqlite    # if file missing
php artisan migrate:fresh --seed
```

CI creates this automatically at `${GITHUB_WORKSPACE}/database/testing.sqlite`.

**Playwright `POST /login` 419:** Dùng `tests/e2e/helpers/loginPost.js`. `playwright.config.js` ép `SESSION_DRIVER=file`, `SESSION_SECURE_COOKIE=false`. Local `.env` `SESSION_DRIVER=redis` cần Redis hoặc đổi `file`.

**`PUT /settings/general` (hoặc tab cấu hình khác) 419 liên tục:** Laravel «Page Expired» = cookie phiên hoặc CSRF không khớp (Inertia `useForm().put`). Kiểm tra:

1. URL trình duyệt **cùng host** với `APP_URL` (không trộn `localhost` ↔ `127.0.0.1`).
2. Local HTTP: `SESSION_SECURE_COOKIE` **false** (prod HTTPS: `true`).
3. `SESSION_DRIVER=file` và thư mục `storage/framework/sessions` ghi được; nếu `redis` thì Redis phải chạy.
4. Không copy `SESSION_DOMAIN` prod (`.vaschools.edu.vn`) vào local — để trống hoặc bỏ key.
5. Hard refresh (Ctrl+F5) hoặc đăng xuất → đăng nhập lại sau khi đổi `APP_KEY`.

Frontend: `HandleInertiaRequests` chia sẻ `csrf_token`; `app.js` reload khi Inertia nhận 419.

---

## AI accounts — badge «1 TK» but 0 VNĐ/month after delete

**Symptoms:** `/ai-accounts` still shows 1 account / 1 active in BA group; monthly cost is 0.

**Cause:** Orphan `AiAccount` row (proposal expired/unlinked/rejected) while account was not soft-deleted — common on legacy data or before sync fixes.

**Fix (app):** Deploy code with `AiAccount::purgeOrphanedFromProposal()` on list load; user hard-refreshes once. See **`docs/AI_ACCOUNTS.md`** (orphan + destroy rules).

**Verify:** `php artisan test tests/Feature/AiAccountOrphanPurgeTest.php tests/Feature/AiAccountSoftDeleteVisibilityTest.php`

---

## Route 404 after deploy (e.g. task import `POST /projects/{id}/tasks/import`)

**Symptoms:** A specific action 404s in production while older actions on the same prefix (e.g. `POST /projects/{id}/sprints`) still work. Code, route definition (`routes/web/{domain}.php`), controller and FormRequest are all present and correct on the deployed commit.

**Cause:** Stale `bootstrap/cache/routes-*.php` on the server. A previous `php artisan route:cache` / `optimize` cached the route table; any route added since (here `tasks.import`, added in `33e3212`) is missing from the cache, so the router never matches it → 404. The deploy step only ran `php artisan config:cache`, which does **not** refresh routes.

**Fix (immediate):**
```bash
php artisan route:clear     # or: php artisan optimize:clear
php artisan route:cache     # re-cache for performance
```

**Fix (permanent):** Deploy must clear caches before re-caching — see `_dev/workflows.md` › Deploy. Run `php artisan optimize:clear` then `config:cache` + `route:cache` + `view:cache`. Never run `config:cache` alone.

---

## Comment realtime — second user does not see new messages

**Symptoms:** User A posts on blocker **Trao đổi**; A sees the comment; User B on the same thread does not until refresh. No green **Realtime** badge.

**Cause (typical on production):** Stack incomplete — one or more of: `REALTIME_ENABLED=false`, Redis not running / Laravel cannot `Redis::publish`, Node `realtime/server.mjs` not running, nginx not proxying `/socket.io/` to port `6001`.

**Fix:** Full checklist in [`_dev/realtime.md`](realtime.md) (nginx snippet, systemd, env vars).

**Quick checks:**

```bash
redis-cli ping
curl -s -o /dev/null -w "%{http_code}" "https://YOUR_APP/socket.io/?EIO=4&transport=polling"
php artisan tinker --execute="echo config('realtime.enabled') ? 'on' : 'off';"
```

**Note:** Poster still gets updates via Inertia `only: ['blockers']`; realtime is for **other** viewers on the same thread.

---

## Toast flash lúc có lúc không (cùng trang, cùng message)

**Symptoms:** Lưu/cập nhật liên tiếp (vd. tiêu chí đánh giá «Đã cập nhật…») — lần đầu có toast, lần sau im.

**Cause:** `AppLayout` watch flash chỉ gọi toast khi `success !== prevSuccess`. Inertia giữ nguyên chuỗi flash giữa hai lần `back()` cùng message → watch không chạy.

**Fix:** Sau khi hiện toast, gán `page.props.flash.{success|error|warning} = null` (`consumeFlashToast` trong `AppLayout.vue`) để lần flash sau (cùng nội dung) vẫn là thay đổi null → message.

---

## SSO HRM — redirect_uri / JWT không hợp lệ

**Symptoms:** Bấm «Đăng nhập tài khoản nhà trường» → lỗi trên HRM (`redirect_uri không nằm trong whitelist`) hoặc callback Workspace flash «Không xác thực được phiên HRM».

**Checklist:**

1. `HRM_SSO_ENABLED=true`, `HRM_SSO_BASE_URL` trỏ đúng host HRM; `php artisan config:clear`.
2. Trên HRM `/admin/api-clients` (client `workspace`): `sso_enabled=true` + redirect URI **khớp tuyệt đối** `{APP_URL}/auth/hrm/callback` (kể cả `http`/`https`, không trailing slash lệch).
3. `HRM_SSO_ISSUER` phía Workspace = `SSO_ISSUER` phía HRM; JWKS: `{HRM}/.well-known/jwks.json` (`php artisan hrm:sso-keys` trên HRM).
4. JWT SSO (user, TTL ~10 phút) ≠ `HRM_API_TOKEN` (M2M Sanctum — luồng khác).

**Verify:** `php artisan test --filter=HrmSsoLoginTest` · docs: `docs/ARCHITECTURE.md` § SSO HRM.

---

## HRM API M2M

**Symptoms:** `hrm:api-ping` 401; login «Email chưa có trong hệ thống nhân sự»; log `hrm.api.http_error` / `hrm.api.find_by_email_failed`.

**Checklist:**

1. Mint token tại HRM `/admin/api-clients` (client `workspace`) → `HRM_API_TOKEN` + `HRM_API_BASE_URL=…/api/v1`.
2. `php artisan config:clear` rồi `php artisan hrm:api-ping` (và `--email=user@…`).
3. Workspace **chỉ** dùng API — không còn `HRM_DB_*` / fallback `va_hrm`. Thiếu token → không lazy upsert được.
4. **JWT SSO (`HRM_SSO_*`) ≠ Bearer `HRM_API_TOKEN`** — không dùng JWT để gọi `/api/v1/*`.
5. Lỗi Google `invalid_client` / `invalid_grant` là OAuth (IdP), không phải token M2M.

**cURL error 60 (SSL certificate / unable to get local issuer):** Host HRM thường thiếu intermediate trong chuỗi cert (cacert Mozilla không verify được). Local ServBay: `HRM_API_VERIFY_SSL=false` rồi `php artisan config:clear`. Production: sửa cert trên server HRM (fullchain), giữ verify=true. Tuỳ chọn `HRM_API_CA_BUNDLE=` nếu có CA nội bộ. Kiểm tra: `php artisan hrm:api-ping`.

---

## Google login — «Đăng nhập Google thất bại trên máy chủ (UnexpectedValueException)»

**Symptoms:** OAuth Google xong (email đúng domain) → flash `…thất bại trên máy chủ (UnexpectedValueException: …). Kiểm tra log auth.google.callback_failed.` Domain reject / HRM missing thì message khác (không phải exception class).

**Ý nghĩa:** Google đã OK; crash trong `GoogleAuthController::completeGoogleLogin` (log / upsert HRM / provision `SystemAccount` / session). Đọc **message** trong flash (hoặc log).

### 1) Hay gặp nhất — không ghi được `storage/logs/laravel.log`

Flash chứa:

`The stream or file "…/storage/logs/laravel.log" could not be opened in append mode: Failed to open stream: Permission denied`

→ **Không phải lỗi Google / Client ID.** PHP-FPM (hoặc LiteSpeed) không ghi được `storage/logs`. Khi đó `grep storage/logs` cũng vô ích — dùng `error_log` / log web server.

**Sửa trên server (ngay):**

```bash
cd /home/projects.vaschools.edu.vn/public_html   # path deploy

# User PHP thường = user vhost (cPanel) hoặc www-data / nobody
ls -la storage storage/logs bootstrap/cache
mkdir -p storage/logs storage/framework/{sessions,views,cache} bootstrap/cache
touch storage/logs/laravel.log

# Điều chỉnh USER:GROUP cho khớp process PHP (vd. projects:projects hoặc www-data:www-data)
chown -R USER:GROUP storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
chmod 664 storage/logs/laravel.log

# Kiểm tra ghi được:
sudo -u USER touch storage/logs/.write-test && rm storage/logs/.write-test
```

Sau đó thử đăng nhập Google lại. Cùng quyền cho upload KB / attachment (xem mục Knowledge base upload 500).

### 2) Đọc `auth.google.callback_failed` (sau khi đã ghi được log)

```bash
cd /home/projects.vaschools.edu.vn/public_html

grep -R "auth.google.callback_failed\|\[auth.google.callback_failed\]" storage/logs/ -n | tail -n 30
# stderr PHP-FPM / LiteSpeed nếu storage/logs chưa ghi được:
grep -R "auth.google.callback_failed" /var/log/ 2>/dev/null | tail -n 20

php artisan tinker --execute="
\$email='ngocntk@hcm.vaschools.edu.vn';
\$e=\App\Models\Employee::withTrashed()->where('email',\$email)->first();
dump(\$e?->only(['id','email','code','hrm_user_id','hrm_employee_uuid','is_active','deleted_at','join_date']));
dump(\App\Models\SystemAccount::where('employee_id',\$e?->id)->first()?->getAttributes());
"

php artisan hrm:api-ping --email=ngocntk@hcm.vaschools.edu.vn
```

**Hay gặp (sau khi quyền log OK):** `role` DB lệch enum; `join_date`/`hired_at` lạ khi refresh HRM; unique `code`/`email`/`hrm_*` khi upsert; thiếu cột sau migrate.

---

## Google login — «Chưa có tài khoản đăng nhập cho nhân sự này»

**Symptoms:** OAuth Google thành công (email đúng domain) → flash «Chưa có tài khoản đăng nhập cho nhân sự này. Liên hệ quản trị.»

**Cause (đã sửa trong `GoogleAuthController`):** Callback chỉ auto-tạo `SystemAccount` khi `employees.hrm_user_id` có giá trị. Identity API-first thường chỉ gắn `hrm_employee_uuid` (không có `legacy_user_id`) → bị chặn dù nhân sự active. `HrmSsoController` đã provision theo cả uuid.

**Fix code:** Căn `GoogleAuthController` với SSO — gọi `SystemAccountProvisioner` khi chưa có account **hoặc** đã liên kết `hrm_user_id` / `hrm_employee_uuid`.

**Kiểm tra dữ liệu (nếu vẫn lỗi sau deploy):**

```bash
php artisan tinker --execute="
\$e = \App\Models\Employee::where('email', 'EMAIL@vaschools.edu.vn')->first();
dump(\$e?->only(['id','email','hrm_user_id','hrm_employee_uuid','is_active']));
dump(\App\Models\SystemAccount::where('employee_id', \$e?->id)->first()?->only(['id','username','is_active','role']));
"
```

Nếu có `Employee` active nhưng không có `SystemAccount` → đăng nhập lại (provision lazy). Nếu không có `Employee` → cần HRM (`hrm:api-ping --email=…`) hoặc tạo nhân sự/liên kết HRM.