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

SQLite E2E chỉ trên job Playwright. PHPUnit dùng stub Vite trong `tests/TestCase` (không cần `npm build` trong job PHP).

**Trước push:** skill `ship-ready` · rule `ci-quality-gates.mdc` · [`../ci-cd.md`](../ci-cd.md) bản đầy đủ.

---

## Các job (chặn merge)

### 1. PHPUnit + Pint (`backend-tests`) ✓

`vendor/bin/pint --test` → `php artisan test`

### 2. ESLint + build (`frontend-build`) ✓

`npm run lint` → `npm run build`

### 3. Playwright E2E (`playwright`) ✓

Sau hai job trên; `CI=true`, một worker, không reuse server :8000 trừ khi `PLAYWRIGHT_REUSE_SERVER=1`.

### 4. PHPStan (`static-analysis`) — chỉ cảnh báo

`continue-on-error: true`

---

## Sơ đồ phụ thuộc job

```
backend-tests (Pint + PHPUnit) ──┐
                                 ├──► playwright
frontend-build (ESLint + build) ─┘

static-analysis (song song, không chặn)
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
| Pint | Chạy tay trước push | Chặn trong `backend-tests` |
| ESLint | pre-commit + `npm run lint` | Chặn trong `frontend-build` |
| Database | `.env` của bạn | SQLite chỉ job E2E |
