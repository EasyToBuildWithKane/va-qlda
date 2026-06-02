# VA-QLDA Documentation Navigator

Tra cứu tài liệu kỹ thuật trong `docs/` để hiểu kiến trúc, API routes, database schema, frontend structure, refactor plan, và technical debt.

## Khi nào dùng

- Hỏi hệ thống hoạt động thế nào, code nằm đâu, cần xây gì tiếp.
- Lập kế hoạch refactor, module mới, hoặc feature cắt ngang nhiều lớp.
- Cần route names, table names, hoặc trách nhiệm từng lớp.

## Doc map (đọc có chọn lọc)

| File | Dùng cho |
|------|---------|
| `docs/PROJECT_OVERVIEW.md` | Modules, flows, roles, stack |
| `docs/ARCHITECTURE.md` | Layers, coupling, target architecture |
| `docs/FRONTEND_STRUCTURE.md` | Pages, components, composables, UI patterns |
| `docs/API_STRUCTURE.md` | Tất cả web routes, Inertia vs JSON |
| `docs/DATABASE_STRUCTURE.md` | Tables, columns, ERD |
| `docs/REFACTOR_PLAN.md` | Phased refactor — không thực thi nếu không được duyệt |
| `docs/TECHNICAL_DEBT.md` | Known issues (TD-001…) |
| `docs/NEXT_STEPS.md` | Roadmap, quick wins |

## Workflow

1. Xác định module (Project, DailyReport, Blocker, …).
2. Mở doc section liên quan + grep codebase (`routes/web.php`, `Pages/`, `Controllers/`).
3. Match **pattern hiện có** (DailyReport = Use Case; Project = MVC).
4. Nếu thay đổi liên quan import/export → đọc thêm phần Nhập/Xuất/Đối soát trong `CLAUDE.md`.

## Project facts (nhanh)

- Auth: `SystemAccount`, roles `admin|lead|member|viewer`.
- Primary transport: Inertia, không phải REST `api.php`.
- Notifications: `app_notifications` + drawer.

## Output

Tóm tắt findings với links đến doc paths và file paths cụ thể trong repo.
