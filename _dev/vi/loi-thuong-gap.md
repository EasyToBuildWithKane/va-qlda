# Lỗi thường gặp & cách xử lý

**File gốc:** [`../troubleshooting.md`](../troubleshooting.md)

Xử lý sự cố dev local, Git hooks, và CI.

---

## Deploy server — EBADENGINE / git pull đã mới nhất

**Tự build sau pull:** `git config core.hooksPath .husky` + `export VA_AUTO_BUILD_ON_PULL=1` — xem `_dev/troubleshooting.md` (Server deploy).

**EBADENGINE:** Pull bản mới (lint-staged 16). Server: `npm ci --omit=dev` khi cần.

**Already up to date:** Máy dev cần `git push` trước; server `git fetch`.

---

## Husky hook không chạy

**Triệu chứng:** Commit không qua ESLint; push không chạy E2E.

**Sửa:**

```bash
npm run prepare
```

**Kiểm tra:**

```bash
ls .husky/
# Cần có: pre-commit, commit-msg, pre-push, post-merge, prepare-commit-msg
```

**Windows:** Hook là shell script — chạy git qua **Git Bash** (đi kèm Git for Windows). Nếu hook im lặng fail, kiểm tra `core.hooksPath` trỏ `.husky`.

**Unix / macOS / WSL:**

```bash
chmod +x .husky/*
```

---

## commitlint báo lỗi

**IDE ghi "Updates" / subject trống:** Hook tự sinh message Conventional từ file staged (`fix-commit-msg.mjs`). Cần `git add` trước. Hoặc `npm run commit`.

**Triệu chứng:** `git commit` bị từ chối.

**Format bắt buộc:** `type(scope): mo ta`

```bash
echo "feat: test message" | npm run commitlint
```

**Types hợp lệ:** feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert

**Quy tắc:** header ≤ 72 ký tự, body line ≤ 100 ký tự, không PascalCase subject.

**Bỏ qua một lần (không khuyến khích):**

```bash
git commit --no-verify -m "feat: sua khan"
```

**Gợi ý message tự động:**

```bash
npm run commit:msg
npm run commit
```

---

## Sync Changes / git push bị chặn

**Triệu chứng:** Sync **rất lâu** hoặc **fail** khi push.

**Mặc định mới:** Push/Sync **không** chạy E2E local (nhanh). GitHub Actions vẫn chạy test.

**Muốn E2E trước push:** `$env:RUN_E2E_ON_PUSH="1"; git push`

**Lỗi cổng 8001:** `node tests/e2e/helpers/stopStaleE2ePorts.js` hoặc `taskkill /F /PID <pid>`.

---

## Playwright: không tìm thấy browser

**Triệu chứng:** `Executable doesn't exist at .../chromium-...`

**Sửa:**

```bash
npm run test:e2e:install
# hoặc
npx playwright install chromium
npx playwright install --with-deps chromium
```

---

## Playwright fail trên máy local

**Checklist:**

1. Port 8000 có bị chiếm không?
2. DB đã migrate + seed?
   ```bash
   php artisan migrate:fresh --seed
   ```
3. Cần build assets?
   ```bash
   npm run build
   ```
4. Debug có giao diện:
   ```bash
   npx playwright test --headed --debug
   ```
5. Xem báo cáo:
   ```bash
   npx playwright show-report
   ```

---

## ESLint chặn commit

**Triệu chứng:** pre-commit fail.

**Tự sửa:**

```bash
npm run lint:fix
git add -u
git commit -m "fix: resolve lint errors"
```

**Sửa thư mục cụ thể:**

```bash
npx eslint --fix resources/js/
```

**Bỏ qua một lần:** `git commit --no-verify`

> Cảnh báo (warning) cũng **chặn commit** vì `--max-warnings=0`.

---

## pre-push E2E chặn push

**Triệu chứng:** `git push` bị hủy — "E2E tests failed."

**Sửa:** chạy trước:

```bash
npm run test:e2e
```

**Bỏ qua khẩn cấp:** `git push --no-verify`

Trên CI runner: pre-push bỏ qua khi `CI=true`.

---

## CI pipeline fail

1. GitHub → Actions → CI → run lỗi
2. Mở job fail (`backend-tests` = Pint+PHPUnit, `frontend-build` = ESLint+build, `playwright`, `static-analysis`)
3. **Re-run failed jobs**
4. Playwright: tải artifact `playwright-report`

**Nguyên nhân thường gặp:**

| Job | Thường do |
|-----|-----------|
| backend-tests | Pint → `vendor/bin/pint`; PHPUnit 500 → `tests/TestCase` Vite stub |
| frontend-build | ESLint → `npm run lint:fix`; lỗi Vite build |
| Playwright | Selector/UI; server :8000 — `playwright.config.js` |
| PHPStan | Cảnh báo, không chặn merge |

**Trước push:** skill `ship-ready`

**Bỏ qua CI khi push:**

```bash
git commit -m "chore: docs [skip ci]"
```

---

## npm install fail sau git pull

**Triệu chứng:** Module not found, peer dependency error.

**PowerShell:**

```powershell
Remove-Item -Recurse -Force node_modules, package-lock.json
npm install
```

**Bash:**

```bash
rm -rf node_modules package-lock.json
npm install
```

Hook `post-merge` tự `npm install` khi `package.json` đổi — nếu fail, chạy tay.

---

## Composer / PHP

```bash
composer install
php artisan config:clear
php artisan cache:clear
php artisan migrate
```

**Class not found sau file mới:**

```bash
composer dump-autoload
```

**Thiếu `.env`:**

```bash
cp .env.example .env
php artisan key:generate
```

---

## Vite dev server

**Triệu chứng:** `@vite/client` 404, trang trắng, HMR không hoạt động.

1. Chạy `npm run dev`
2. Nếu không dùng dev server → `npm run build`
3. Xóa cache browser
4. Restart: Ctrl+C → `npm run dev`

Alias `@/` → `resources/js/` trong `vite.config.js`.

---

## Lỗi SQLite / database khi test

E2E dùng config từ `tests/e2e/helpers/database.js`:

```bash
touch database/testing.sqlite
php artisan migrate:fresh --seed
```

CI tự tạo tại `database/testing.sqlite` trong workspace.

---

## Quản lý AI — vẫn «1 TK», chi phí 0 sau khi xóa

**Triệu chứng:** `/ai-accounts` hoặc `/ai-accounts/cost-by-group` vẫn badge 1, nhóm BA «1 hoạt động», chi phí/tháng 0 VNĐ.

**Nguyên nhân:** TK mồ côi (PĐX hết hạn / gỡ liên kết) còn trong DB.

**Xử lý:** Deploy bản có `purgeOrphanedFromProposal`, user F5 một lần. Chi tiết: [`docs/AI_ACCOUNTS.md`](../../docs/AI_ACCOUNTS.md). File gốc EN: [`../troubleshooting.md`](../troubleshooting.md) mục AI accounts.

---

## Route 404 sau khi deploy (vd nhập task `POST /projects/{id}/tasks/import`)

**Hiện tượng:** Một hành động bị 404 trên production, trong khi hành động cũ cùng prefix (vd `POST /projects/{id}/sprints`) vẫn chạy. Code, định nghĩa route (`routes/web.php`), controller, FormRequest đều có và đúng ở commit đã deploy.

**Nguyên nhân:** File `bootstrap/cache/routes-*.php` trên server còn cũ. Lần `php artisan route:cache` / `optimize` trước đã cache bảng route; route thêm sau đó (ở đây là `tasks.import`, thêm ở `33e3212`) không có trong cache nên router không khớp → 404. Bước deploy chỉ chạy `config:cache` — lệnh này **không** làm mới route.

**Sửa ngay:**
```bash
php artisan route:clear     # hoặc: php artisan optimize:clear
php artisan route:cache     # cache lại cho hiệu năng
```

**Sửa triệt để:** Deploy phải xoá cache trước khi cache lại — xem `_dev/vi/quy-trinh.md` › Deploy. Chạy `php artisan optimize:clear` rồi `config:cache` + `route:cache` + `view:cache`. Đừng chỉ chạy mỗi `config:cache`.
