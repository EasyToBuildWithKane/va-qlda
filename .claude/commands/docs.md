# VA-Workspace Documentation Navigator

Tra cứu `docs/` (kiến trúc) và `_dev/` (vận hành: CLI, CI, Husky, workflows).

## Doc map

| File | Dùng cho |
|------|---------|
| `docs/PROJECT_OVERVIEW.md` | Modules, flows, roles |
| `docs/FOLDER_STRUCTURE.md` | Cấu trúc folder sau refactor |
| `docs/FRONTEND_STRUCTURE.md` | modules/, shared/, composables, Pinia |
| `docs/ARCHITECTURE.md` | Layers, coupling |
| `docs/REFACTOR_PLAN.md` | Phase 1–5 ✅ |
| `docs/TECHNICAL_DEBT.md` | TD còn lại + roadmap |
| `docs/CONTRACT_MANAGEMENT.md` | Hợp đồng / NCC (CLM) |
| `docs/CREDENTIAL_MANAGEMENT.md` | Kho tài khoản / mật khẩu |
| `docs/PERFORMANCE_ANALYTICS.md` | Hiệu suất & audit |
| `docs/ONBOARDING.md` | Tour tương tác |

## Operational memory (`_dev/`)

| Câu hỏi | File |
|---------|------|
| Lệnh CLI | `_dev/commands.md` / `_dev/vi/lenh-cli.md` |
| Quy trình | `_dev/workflows.md` / `_dev/vi/quy-trinh.md` |
| CI / Husky | `_dev/ci-cd.md` |

## Facts (2026-06-19)

- Refactor Phase 1–5: **done**
- Frontend: 13 feature module dưới `modules/` (project, daily-report, knowledge-base, …), `shared/ui/`, Pinia
- Backend: Project/Task Use Cases + Options services
- Routes split: `routes/web.php` (loader) → `routes/web/{domain}.php` (16 partial)
- `Components/Project|DailyReport|KnowledgeBase/` **migrated** → `modules/`

## Workflow

1. Xác định module → đọc doc + grep code
2. DailyReport = Use Case + Domain; Project/Task mutations = Use Case; Blocker = MVC
3. Import/export → rule Nhập/Xuất/Đối soát trong CLAUDE.md
