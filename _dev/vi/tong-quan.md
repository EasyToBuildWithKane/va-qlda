# Tổng quan — bộ nhớ dự án `_dev/`

**File gốc:** [`../README.md`](../README.md)

---

## `_dev/` dùng để làm gì?

Đây là **bộ nhớ vận hành** của repo VA-QLDA — nơi lưu lệnh, quy trình, CI, test, xử lý lỗi thường gặp. Mục tiêu: dev mới (hoặc AI) không phải đào lại `package.json`, `.husky/`, `.github/workflows/` mỗi lần.

## Cấu trúc thư mục

```
_dev/
├── README.md, commands.md, workflows.md, …   ← tiếng Anh, nguồn chuẩn
└── vi/                                         ← giải thích tiếng Việt
    ├── README.md
    └── tong-quan.md                            ← file này
```

## Đọc file nào?

| Bạn cần | File VI | File gốc (EN) |
|---------|---------|---------------|
| Chạy lệnh (npm, artisan, test) | [lenh-cli.md](lenh-cli.md) | [commands.md](../commands.md) |
| Quy trình hàng ngày / PR / hotfix | [quy-trinh.md](quy-trinh.md) | [workflows.md](../workflows.md) |
| Format commit, đặt tên branch | [quy-uoc.md](quy-uoc.md) | [conventions.md](../conventions.md) |
| CI GitHub Actions fail | [ci-cd.md](ci-cd.md) | [ci-cd.md](../ci-cd.md) |
| Viết / chạy Playwright | [kiem-thu.md](kiem-thu.md) | [testing.md](../testing.md) |
| Hook Husky, ESLint, lỗi npm… | [loi-thuong-gap.md](loi-thuong-gap.md) | [troubleshooting.md](../troubleshooting.md) |
| Tổng quan nhanh | [tong-quan.md](tong-quan.md) | [README.md](../README.md) |

## Stack tóm tắt

- **Backend:** Laravel 10, PHP 8.1+, MySQL (bảng prefix `va_prd_`)
- **Frontend:** Vue 3 + Vite + Inertia, code trong `resources/js/`
- **Chất lượng:** ESLint (pre-commit), commitlint, Pint, PHPStan, Playwright (pre-push + CI)
- **CI:** `.github/workflows/ci.yml` — PHPUnit, build frontend, E2E, Pint, PHPStan

## Lệnh hay dùng nhất

```bash
npm run dev              # Vite — giao diện hot reload
php artisan serve        # Laravel — http://127.0.0.1:8000
npm run lint             # Kiểm tra ESLint (cảnh báo = lỗi)
composer test            # PHPUnit
npm run test:e2e         # E2E Playwright (cũng chạy khi git push)
php artisan migrate      # Migration mới sau git pull
```

## Luồng làm việc ngắn

1. `git pull` → cài dependency nếu lockfile đổi → `php artisan migrate` nếu có migration mới
2. Hai terminal: `npm run dev` + `php artisan serve`
3. Nhánh `feat/…` hoặc `fix/…` → code → commit (Husky lint + commitlint) → push (Husky chạy E2E)
4. Mở PR → CI chạy trên GitHub → merge squash vào `main`

## Ghi chú quan trọng

- **Chưa có workflow deploy tự động** — merge xong deploy thủ công (ServBay / server).
- **ESLint chặn ở pre-commit**, không có job ESLint riêng trên CI.
- **Pint và PHPStan trên CI** là advisory (`continue-on-error`) — không chặn merge nhưng nên sửa.
- UI / thông báo lỗi trong app: **tiếng Việt**; commit message: **Conventional Commits** (tiếng Anh).

## Cập nhật tài liệu

Khi thêm npm script, Husky hook, hoặc job CI mới → cập nhật file gốc `_dev/*.md` **cùng lúc** với code. Nếu team cần giải thích VI → thêm hoặc sửa file trong `_dev/vi/`.
