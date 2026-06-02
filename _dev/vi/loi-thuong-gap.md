# Lỗi thường gặp & cách xử lý

**File gốc:** [`../troubleshooting.md`](../troubleshooting.md)

Xử lý sự cố dev local, Git hooks, và CI.

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
2. Mở job fail (PHPUnit, build, Playwright, Pint, PHPStan)
3. **Re-run failed jobs**
4. Playwright: tải artifact `playwright-report`

**Nguyên nhân thường gặp:**

| Job | Thường do |
|-----|-----------|
| PHPUnit | Migration thiếu, assertion fail, env |
| Frontend build | Lỗi Vite, import sai |
| Playwright | UI đổi → selector hỏng, seed data lệch |
| Pint | Format PHP lệch → `composer format` local |
| PHPStan | Vi phạm type / static analysis |

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
