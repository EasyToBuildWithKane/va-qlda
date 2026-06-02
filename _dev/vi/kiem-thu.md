# Kiểm thử — Playwright E2E (tiếng Việt)

**File gốc:** [`../testing.md`](../testing.md)

Test end-to-end cho luồng UI VA-QLDA. Config: `playwright.config.js`.

---

## Cài đặt lần đầu

```bash
npm run test:e2e:install
# tương đương: npx playwright install --with-deps chromium
```

Cần Node 20+, PHP 8.1+, `composer install` đã chạy.

---

## Tổng quan config

| Cấu hình | Giá trị |
|----------|---------|
| Thư mục test | `./tests/e2e` |
| Base URL | `PLAYWRIGHT_BASE_URL` hoặc mặc định `http://127.0.0.1:8000` |
| Browser | Chỉ Chromium (Desktop Chrome) |
| Global setup | `./tests/e2e/global-setup.js` |
| Song song | `fullyParallel: true` |
| Retry | 0 local · 2 trên CI |
| Workers | auto local · 1 trên CI |

### Reporter

| Môi trường | Reporter |
|------------|----------|
| Local | `list` + `html` (mở khi fail) |
| CI | `github` + `html` (không tự mở) |

### Trace / screenshot / video

| Cấu hình | Giá trị |
|----------|---------|
| Trace | `on-first-retry` |
| Screenshot | `only-on-failure` |
| Video | `retain-on-failure` |

### Web server (tự khởi động)

Playwright tự chạy Laravel trước khi test:

```
php artisan serve --host=127.0.0.1 --port=8000
```

- Local: `reuseExistingServer: true` — dùng lại server đang chạy
- CI: luôn start server mới

Env DB E2E lấy từ `tests/e2e/helpers/database.js`.

---

## Spec hiện có

| File | Phạm vi |
|------|---------|
| `tests/e2e/auth.spec.js` | Trang login hiển thị, member đăng nhập → dashboard, sai mật khẩu |

Thêm spec mới: `tests/e2e/<ten-tinh-nang>.spec.js`

---

## Chạy test

```bash
npm run test:e2e                              # Tất cả, headless
npm run test:e2e:ui                             # GUI tương tác
npx playwright test --headed                    # Nhìn thấy browser
npx playwright test tests/e2e/auth.spec.js      # Một file
npx playwright test --grep "login"              # Lọc theo tên
npx playwright test --debug                     # Debug từng bước
npx playwright codegen http://127.0.0.1:8000    # Ghi test mới
npx playwright show-report                      # Báo cáo HTML
```

**Mẹo:** Nếu đã có `php artisan serve` port 8000, Playwright tái sử dụng — không cần tắt.

---

## Tích hợp CI

Workflow `.github/workflows/ci.yml` → job `playwright`:

1. Chạy sau PHPUnit + frontend build pass
2. Tạo SQLite mới + `migrate:fresh --force --seed`
3. Cài Chromium
4. `npm run test:e2e`
5. Fail → upload artifact 7 ngày

**Hook pre-push** cũng chạy E2E trước mỗi `git push` (trừ `CI=true`).

---

## PHPUnit (backend)

Tách biệt với Playwright:

```bash
composer test
php artisan test --filter=AuthenticationTest
```

Job CI `backend-tests` chạy trước Playwright.

---

## Artifact / báo cáo

| Đường dẫn | Nội dung |
|-----------|----------|
| `playwright-report/` | Báo cáo HTML sau mỗi lần chạy |
| `test-results/` | Screenshot, video, trace khi fail |

Cả hai thư mục **gitignore**. Trên CI: tải từ artifact `playwright-report`.

---

## Viết test mới

```js
import { test, expect } from '@playwright/test';

test.describe('Ten tinh nang', () => {
    test('lam gi do', async ({ page }) => {
        await page.goto('/duong-dan');
        await expect(page.getByRole('heading', { name: 'Tieu de' })).toBeVisible();
    });
});
```

**Khuyến nghị:** dùng `getByRole`, `getByLabel` thay vì CSS selector — theo pattern `auth.spec.js`.

**Tài khoản test (seeder):** username `member`, password `password`.

**Khi sửa UI:** cập nhật selector trong spec tương ứng — nếu không pre-push và CI sẽ fail.
