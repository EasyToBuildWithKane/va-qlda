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

## Playwright tests fail locally

**Checklist:**

1. Is port 8000 free? Playwright starts `php artisan serve` unless one is already running
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
git push --no-verify
```

**In CI environments:** pre-push is skipped when `CI=true`.

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
