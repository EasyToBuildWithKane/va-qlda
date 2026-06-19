# Lệnh CLI — hướng dẫn tiếng Việt

**File gốc:** [`../commands.md`](../commands.md)

Chạy mọi lệnh từ **thư mục gốc** repo trừ khi ghi chú khác.

---

## Phát triển (Development)

| Lệnh | Giải thích |
|------|------------|
| `npm run dev` | Vite — hot reload `resources/js/`, `resources/css/app.css` |
| `php artisan serve` | Laravel tại `http://127.0.0.1:8000` |
| `php artisan migrate` | Migration chưa chạy |
| `php artisan migrate:fresh --seed` | Xóa DB → migrate → seed (dev/CI) |

**Setup local:** 2 terminal — `npm run dev` + `php artisan serve`.

**Vite** (`vite.config.js`): entry `app.css` + `app.js`; alias `@` → `resources/js/`.

---

## Build & realtime

| Lệnh | Giải thích |
|------|------------|
| `npm run build` | Build production → `public/build/` |
| `npm run realtime` | Server Socket.IO (`realtime/server.mjs`) — cần Redis |
| `npm run realtime:dev` | Giống trên + `--watch` file server |

Preview build: dùng `npx vite preview` (chưa có `npm run preview`).

---

## Lint & format

| Lệnh | Giải thích |
|------|------------|
| `npm run lint` | ESLint — **cảnh báo = lỗi** (`--max-warnings=0`) |
| `npm run lint:fix` | ESLint tự sửa |
| `npx lint-staged` | Chỉ file staged (giống pre-commit) |
| `vendor/bin/pint --test` | Pint kiểm tra PHP (**CI blocking**) |
| `vendor/bin/pint` | Pint sửa PHP |
| `composer format` / `format:test` | Alias Pint |
| `composer analyse` | PHPStan (CI advisory) |

---

## Git hooks (Husky)

Cài lại: `npm run prepare` → `node node_modules/husky/bin.js`.

| Hook | Khi nào | Việc làm |
|------|---------|----------|
| **pre-commit** | Trước commit | `lint-staged` — ESLint fix JS/Vue staged |
| **prepare-commit-msg** | Trước editor message | Gợi ý message từ diff (bỏ qua merge/squash) |
| **commit-msg** | Sau khi gõ message | commitlint — Conventional Commits |
| **pre-push** | Trước push | **Mặc định bỏ qua E2E**; bật: `RUN_E2E_ON_PUSH=1` |
| **post-merge** | Sau `git pull` | `VA_AUTO_BUILD_ON_PULL=1` → có thể `npm install` + `npm run build` trên server |

| Lệnh hỗ trợ | Mục đích |
|-------------|----------|
| `npm run commitlint` | Test message |
| `npm run commit:msg` | In message suy từ diff (header + body) |
| `npm run commit` | Auto-commit: box tóm tắt + danh sách file kèm churn `+/-`, rồi commit |
| `npm run push:e2e` | Push kèm E2E local |
| `npm run e2e:stop-stale` | Dọn `php artisan serve` cổng 8001–8020 |

---

## Playwright E2E

Config: `playwright.config.js` · `tests/e2e/` · base URL `http://127.0.0.1:8000`.

| Lệnh | Giải thích |
|------|------------|
| `npm run test:e2e` | Luồng UI (CI) — bỏ qua `smoke/`, `visual/` |
| `npm run test:e2e:visual` | So sánh snapshot UI |
| `npm run test:e2e:visual:update` | Cập nhật baseline sau đổi UI cố ý |
| `npm run test:e2e:smoke` | Chụp full-page (không snapshot CI) |
| `npm run test:e2e:ui` | UI mode tương tác |
| `npm run test:e2e:install` | Cài Chromium + deps OS |
| `npx playwright test --debug` | Debug từng bước |
| `npx playwright show-report` | Báo cáo HTML lần chạy trước |

Playwright tự chạy `php artisan serve` qua `webServer`; local có thể tái sử dụng server :8000.

---

## PHPUnit

| Lệnh | Giải thích |
|------|------------|
| `composer test` | = `php artisan test` |
| `php artisan test --filter=TênTest` | Một class/method |

`tests/TestCase.php` tạo stub `public/build/manifest.json` để Inertia không 500 trên job PHPUnit (không cần `npm build` trong job đó).

---

## Tương đương CI (local)

| Job CI | Lệnh local |
|--------|------------|
| `backend-tests` | `vendor/bin/pint --test` → `php artisan test` |
| `frontend-build` | `npm ci` → `npm run lint` → `npm run build` |
| `playwright` | `npm run test:e2e:install` → `npm run build` → `npm run test:e2e` |
| `static-analysis` | `composer analyse` |

Re-run: GitHub → Actions → **Re-run failed jobs**.  
Skip CI: `[skip ci]` hoặc `[ci skip]` trong commit message.

---

## Artisan thường dùng

```bash
php artisan route:list
php artisan make:model Ten -mcr
php artisan storage:link          # public/storage → storage/app/public
php artisan key:generate
php artisan telegram:list-chats   # Lấy chat_id Telegram sau khi bot nhận tin
php artisan migrate:fresh --force --seed
php artisan optimize:clear        # Trước config/route cache trên server
php artisan config:cache
php artisan route:cache
```

---

## Cài dependency

```bash
composer install
npm install          # dev
npm ci               # sạch theo lockfile (CI / sau pull đổi lock)
```

`post-merge` tự `npm install` khi `package.json` đổi.
