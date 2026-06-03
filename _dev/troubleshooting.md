# Troubleshooting — VA-QLDA

Common issues and fixes for local dev, Git hooks, and CI.

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

**Checklist:**

1. Default E2E port is **8000** (`npm run test:e2e`). Pre-push uses **8001** so dev server on 8000 can stay running.
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
