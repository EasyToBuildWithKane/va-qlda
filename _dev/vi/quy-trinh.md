# Quy trình làm việc — hướng dẫn tiếng Việt

**File gốc:** [`../workflows.md`](../workflows.md)

Hướng dẫn từng bước: dev hàng ngày, tính năng mới, PR, deploy, hotfix.

---

## Phát triển hàng ngày

1. `git pull origin main` (hoặc nhánh base của team)
2. `npm install && composer install` — **chỉ khi** `package.json` / `composer.lock` đổi  
   *(Hook `post-merge` tự `npm install` nếu `package.json` thay đổi)*
3. `php artisan migrate` — nếu có migration mới
4. Mở 2 terminal:
   - Terminal 1: `npm run dev` (Vite HMR)
   - Terminal 2: `php artisan serve` (port 8000)
5. Truy cập `http://127.0.0.1:8000`

**Kiểm tra chất lượng trước commit (tùy chọn):**

```bash
npm run lint
composer test
npm run test:e2e        # cũng tự chạy khi git push
```

---

## Tạo tính năng mới

1. `git checkout -b feat/mo-ta-ngan`
2. Code backend (Controller, FormRequest, Policy) và/hoặc frontend (Page, Component, composable)
3. **Nếu đổi UI:** thêm/sửa spec Playwright trong `tests/e2e/`
4. `git add -p` — stage có chọn lọc
5. `git commit -m "feat(scope): mo ta"`
   - **pre-commit** → ESLint qua lint-staged
   - **commit-msg** → commitlint kiểm tra format
   - *(tùy chọn)* **prepare-commit-msg** gợi ý message
6. `git push origin feat/mo-ta-ngan`
   - **pre-push** → chạy full Playwright E2E
7. Mở PR trên GitHub → CI chạy tự động (xem [`ci-cd.md`](ci-cd.md))

---

## Quy trình Pull Request

- **Tiêu đề PR** theo [Conventional Commits](https://www.conventionalcommits.org/) — cùng rule với commitlint
- **Nhánh đích:** `main`, `master`, hoặc `develop`
- **CI bắt buộc pass** (`.github/workflows/ci.yml`):
  - PHPUnit (`php artisan test`)
  - Build frontend (`npm run build`)
  - Playwright E2E (chạy sau khi 2 job trên pass)
- **CI khuyến nghị** (`continue-on-error: true` — hiện cảnh báo, **không chặn merge**):
  - Laravel Pint (style PHP)
  - PHPStan (phân tích tĩnh)
- **ESLint** chỉ chặn ở pre-commit local — **không có job ESLint riêng trên CI**
- **Merge:** ưu tiên **squash merge** để history gọn
- **Review:** sửa feedback → push lại → pre-push chạy E2E lại

---

## Deploy (staging / production)

**Hiện chưa có workflow deploy** trong `.github/workflows/` — chỉ có workflow `CI`.

CI chỉ **kiểm tra chất lượng code**; deploy **thủ công hoặc qua hệ thống bên ngoài** (ServBay, Forge, SSH…).

**GitLab:** `.gitlab-ci.yml` mirror cùng các stage test/build/e2e/quality — cũng **không có stage deploy**.

**Quy trình deploy thủ công (gợi ý):**

1. Merge PR vào `main` sau khi CI pass
2. Trên server: `git pull`
3. Chạy:
   ```bash
   composer install --no-dev
   npm ci && npm run build
   php artisan migrate --force
   php artisan config:cache
   ```

---

## Hotfix (sửa khẩn production)

1. `git checkout -b fix/mo-ta-loi main`
2. Sửa tối thiểu + thêm test regression nếu cần
3. Kiểm tra local:
   ```bash
   composer test
   npm run lint
   npm run test:e2e
   ```
4. `git commit -m "fix(scope): mo ta"`
5. `git push origin fix/mo-ta-loi`
6. Mở PR vào `main` — ưu tiên review nhanh
7. Deploy thủ công lên production

---

## Script commit tự động (tùy chọn)

| Script | Lệnh | Mục đích |
|--------|------|----------|
| `scripts/prepare-commit-msg.mjs` | *(hook Husky)* | Điền sẵn message từ diff |
| `scripts/generate-commit-msg.mjs` | `npm run commit:msg` | In message gợi ý |
| `scripts/auto-commit.mjs` | `npm run commit` | Stage + commit tự động |

Dùng khi muốn AI gợi ý commit message; commit thủ công với `-m` vẫn hoạt động bình thường.
