# VA-QLDA Documentation Navigator

Tra cứu `docs/` (kiến trúc) và `_dev/` (vận hành: CLI, CI, Husky, workflows).

## Doc map

| File | Dùng cho |
|------|---------|
| `docs/PROJECT_OVERVIEW.md` | Modules, flows, roles |
| `docs/FOLDER_STRUCTURE.md` | Cấu trúc folder sau refactor |
| `docs/FRONTEND_STRUCTURE.md` | modules/, shared/, composables, Pinia |
| `docs/ARCHITECTURE.md` | Layers, coupling |
| `docs/REFACTOR_PLAN.md` | Phase 1–5 ✅ |
| `docs/TECHNICAL_DEBT.md` | TD còn lại |
| `docs/NEXT_STEPS.md` | Roadmap |

## Operational memory (`_dev/`)

| Câu hỏi | File |
|---------|------|
| Lệnh CLI | `_dev/commands.md` / `_dev/vi/lenh-cli.md` |
| Quy trình | `_dev/workflows.md` / `_dev/vi/quy-trinh.md` |
| CI / Husky | `_dev/ci-cd.md` |

## Facts (2026-06-03)

- Refactor Phase 1–5: **done**
- Frontend: `modules/project/`, `shared/ui/`, Pinia
- Backend: Project/Task Use Cases + Options services
- `Components/Project/` **removed**

## Workflow

1. Xác định module → đọc doc + grep code
2. DailyReport = Use Case + Domain; Project/Task mutations = Use Case; Blocker = MVC
3. Import/export → rule Nhập/Xuất/Đối soát trong CLAUDE.md
