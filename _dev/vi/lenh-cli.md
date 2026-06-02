# Lệnh CLI — hướng dẫn tiếng Việt

**File gốc:** [`../commands.md`](../commands.md)

Chạy mọi lệnh từ **thư mục gốc** dự án trừ khi ghi chú khác.

---

## Phát triển (Development)

| Lệnh | Giải thích |
|------|------------|
| `npm run dev` | Khởi động Vite — hot reload cho giao diện (`resources/js/`, `resources/css/app.css`) |
| `php artisan serve` | Khởi động Laravel tại `http://127.0.0.1:8000` |
| `php artisan migrate` | Chạy migration chưa áp dụng |
| `php artisan migrate:fresh --seed` | Xóa hết bảng → migrate lại → seed dữ liệu mẫu |

**Setup local thông thường:** mở **2 terminal**:

- Terminal 1: `npm run dev`
- Terminal 2: `php artisan serve`

**Vite** (`vite.config.js`):

- Entry: `resources/css/app.css`, `resources/js/app.js`
- Alias `@` → `resources/js/` (import kiểu `@/Components/...`)

---

## Build & tài nguyên

| Lệnh | Giải thích |
|------|------------|
| `npm run build` | Build production → output `public/build/` |
| `vite preview` | Xem bản build local *(chưa có script `npm run preview` trong `package.json`)* |

---

## Lint & format code

| Lệnh | Giải thích |
|------|------------|
| `npm run lint` | ESLint toàn bộ `resources/js/` — **cảnh báo cũng tính là lỗi** (`--max-warnings=0`) |
| `npm run lint:fix` | ESLint tự sửa được |
| `npx lint-staged` | Chỉ lint file đã `git add` (giống hook pre-commit) |
| `composer format` | Laravel Pint — format PHP |
| `composer format:test` | Pint kiểm tra không sửa (`--test`) |
| `composer analyse` | PHPStan phân tích tĩnh |

**lint-staged** (`package.json`): file staged `resources/js/**/*.{js,ts,jsx,tsx,vue}` → `eslint --fix --max-warnings=0`.

---

## Git hooks (Husky)

Hook nằm trong `.husky/`. Cài lại: `npm run prepare`.

| Hook | Khi nào chạy | Làm gì |
|------|--------------|--------|
| **pre-commit** | Trước khi tạo commit | `lint-staged` — ESLint fix file JS/Vue đang staged |
| **commit-msg** | Sau khi viết message | `commitlint` — kiểm tra format Conventional Commits |
| **prepare-commit-msg** | Trước khi mở editor commit | Gợi ý message từ diff (`scripts/prepare-commit-msg.mjs`); bỏ qua nếu merge/squash hoặc user đã gõ sẵn |
| **pre-push** | Trước `git push` | Chạy Playwright E2E; **bỏ qua khi `CI=true`**; fail → hủy push |
| **post-merge** | Sau `git pull` / merge | Nếu `package.json` đổi → tự `npm install` |

**Script hỗ trợ commit:**

| Lệnh | Mục đích |
|------|----------|
| `npm run commitlint` | Kiểm tra message thủ công |
| `npm run commit:msg` | In message gợi ý từ staged changes |
| `npm run commit` | Tự stage + commit (`scripts/auto-commit.mjs`) |

---

## Test Playwright (E2E)

Config: `playwright.config.js` — thư mục test `tests/e2e/`, URL `http://127.0.0.1:8000`.

| Lệnh | Giải thích |
|------|------------|
| `npm run test:e2e` | Chạy tất cả E2E (Chromium headless) |
| `npm run test:e2e:ui` | Giao diện tương tác |
| `npm run test:e2e:install` | Cài Chromium + thư viện OS |
| `npx playwright test --debug` | Debug từng bước |
| `npx playwright test tests/e2e/auth.spec.js` | Một file spec |
| `npx playwright test --grep "login"` | Lọc theo tên test |
| `npx playwright test --headed` | Mở browser nhìn thấy được |
| `npx playwright codegen http://127.0.0.1:8000` | Ghi test mới |
| `npx playwright show-report` | Xem báo cáo HTML lần chạy trước |

**Lưu ý:** Playwright tự khởi động `php artisan serve` qua config `webServer`; local thì tái sử dụng server đang chạy sẵn.

---

## Test backend (PHPUnit)

| Lệnh | Giải thích |
|------|------------|
| `composer test` | Tương đương `php artisan test` |
| `php artisan test` | Chạy toàn bộ PHPUnit |
| `php artisan test --filter=TestName` | Một class/method |

---

## Tương đương CI trên máy local

Workflow: `.github/workflows/ci.yml`

| Job CI | Chạy local |
|--------|------------|
| PHPUnit | `composer test` |
| Frontend build | `npm ci && npm run build` |
| Playwright E2E | `npm run test:e2e:install && npm run test:e2e` |
| Laravel Pint | `composer format:test` |
| PHPStan | `composer analyse` |

**Chạy lại CI fail:** GitHub → Actions → chọn run → **Re-run failed jobs**.

**Bỏ qua CI khi push:** thêm `[skip ci]` vào commit message.

---

## Artisan thường dùng

```bash
php artisan make:model ModelName -mcr    # Model + migration + controller + resource
php artisan make:controller Name --resource
php artisan route:list
php artisan config:cache
php artisan cache:clear
php artisan queue:work
php artisan key:generate                 # Lần đầu setup .env
php artisan migrate:fresh --force --seed # Reset DB (CI / dev)
```

---

## Cài dependency

```bash
composer install    # PHP
npm install         # Node (dev local)
npm ci              # Cài sạch theo lockfile (CI / sau khi lock đổi)
```

Sau `git pull`, nếu `package.json` đổi, hook `post-merge` tự chạy `npm install`.
