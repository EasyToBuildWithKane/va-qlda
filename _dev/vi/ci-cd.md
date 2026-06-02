# CI/CD GitHub Actions — hướng dẫn tiếng Việt

**File gốc:** [`../ci-cd.md`](../ci-cd.md)

Một workflow: `.github/workflows/ci.yml`  
Mirror GitLab: `.gitlab-ci.yml` (cùng stage, cú pháp runner khác).

---

## Workflow: CI

| Thuộc tính | Giá trị |
|------------|---------|
| **File** | `.github/workflows/ci.yml` |
| **Tên** | `CI` |

### Kích hoạt (triggers)

| Sự kiện | Nhánh |
|---------|-------|
| `push` | `main`, `master`, `develop` |
| `pull_request` | → `main`, `master`, `develop` |
| `workflow_dispatch` | Chạy thủ công trên GitHub Actions UI |

### Concurrency

Push mới cùng nhánh → **hủy** run CI đang chạy (`cancel-in-progress: true`).

### Biến môi trường global

| Biến | Giá trị |
|------|---------|
| `APP_ENV` | `testing` |
| `APP_DEBUG` | `true` |
| `APP_URL` | `http://127.0.0.1:8000` |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `${{ github.workspace }}/database/testing.sqlite` |

**Không cần secret** — dùng SQLite file + `.env.example`.

---

## Các job

### 1. PHPUnit (`backend-tests`)

- **OS:** ubuntu-latest · **PHP 8.2**
- **Chặn merge:** Có ✓

**Các bước:** checkout → cài Composer → copy `.env.example` → `key:generate` → `php artisan test`

---

### 2. Frontend build (`frontend-build`)

- **OS:** ubuntu-latest · **Node 20**
- **Chặn merge:** Có ✓

**Các bước:** checkout → `npm ci` → `npm run build`

---

### 3. Playwright E2E (`playwright`)

- **Chờ:** `backend-tests` + `frontend-build` pass
- **Chặn merge:** Có ✓

**Các bước:**

1. Cài Composer + npm
2. `npm run build`
3. Chuẩn bị Laravel: `.env`, key, tạo `database/testing.sqlite`, `migrate:fresh --force --seed`
4. `npx playwright install --with-deps chromium`
5. `npm run test:e2e`

**Env riêng:** `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8000`

**Khi fail:** upload artifact `playwright-report` + `test-results/` (giữ 7 ngày)

Playwright tự start `php artisan serve` qua `webServer`; CI đặt `reuseExistingServer: false`.

---

### 4. Laravel Pint (`code-style`)

- **Chặn merge:** Không (`continue-on-error: true`) — chỉ cảnh báo

Chạy `vendor/bin/pint --test` (kiểm tra style, không sửa file).

---

### 5. PHPStan (`static-analysis`)

- **Chặn merge:** Không (`continue-on-error: true`) — chỉ cảnh báo

Chạy `vendor/bin/phpstan analyse --no-progress --memory-limit=512M`.

---

## Sơ đồ phụ thuộc job

```
backend-tests ──┐
                ├──► playwright
frontend-build ─┘

code-style       (song song, độc lập)
static-analysis  (song song, độc lập)
```

---

## Deploy

**Không có job deploy.** CI chỉ validate code.

Deploy staging/production xử lý **ngoài** repo GitHub Actions này.

---

## Chạy lại workflow bị fail

1. GitHub → **Actions** → **CI**
2. Chọn run bị lỗi
3. **Re-run failed jobs** (hoặc Re-run all jobs)
4. Playwright fail → tải artifact `playwright-report`

---

## Bỏ qua CI

Thêm vào commit message:

```
chore: cap nhat docs [skip ci]
fix: typo [ci skip]
```

Hoặc chạy thủ công qua `workflow_dispatch`.

> **Lưu ý:** Bỏ qua CI **không** bỏ qua Husky local — pre-commit ESLint và pre-push E2E vẫn chạy trừ khi dùng `--no-verify`.

---

## Khác biệt local vs CI

| Khía cạnh | Local | CI |
|-----------|-------|-----|
| Playwright retries | 0 | 2 |
| Playwright workers | auto | 1 |
| Reporter | list + html | github + html |
| pre-push E2E | Chạy (trừ `CI=true`) | Bỏ qua (CI có job riêng) |
| ESLint | pre-commit | Không có job CI |
| Database | `.env` của bạn | SQLite `database/testing.sqlite` |
