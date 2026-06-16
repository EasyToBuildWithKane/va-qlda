# Cursor — VA-QLDA

Cấu hình AI cho repo **VAschools QLDA**, đồng bộ với [`docs/`](../docs/) và [`_dev/`](../_dev/).

## Rules (`.cursor/rules/`)

| File | Áp dụng |
|------|---------|
| `va-qlda-core.mdc` | Luôn — stack, auth, kiến trúc, `_dev/` memory |
| `laravel-backend.mdc` | `app/`, `routes/`, `database/` PHP |
| `vue-inertia-frontend.mdc` | `resources/js/**` |
| `database-schema.mdc` | Migrations & models |
| `import-export-reconcile.mdc` | Nhập / xuất / đối soát Excel → [`docs/IMPORT_EXPORT_RECONCILE.md`](../docs/IMPORT_EXPORT_RECONCILE.md) |
| `backend-code-review-isc.mdc` | Review backend PHP theo ISC + bảo mật |
| `frontend-code-review-isc.mdc` | Review frontend Vue/Inertia theo ISC + bảo mật |

## Skills (`.cursor/skills/`)

| Skill | Khi dùng |
|-------|----------|
| `va-qlda-docs` | Tra cứu `docs/` + `_dev/` |
| `backend-code-review` | Review `git diff --staged` (BE) |
| `frontend-code-review` | Review `git diff --staged` (FE) |
| `add-laravel-feature` | Thêm backend feature |
| `add-vue-page` | Thêm page / component Inertia |
| `daily-report-domain` | Module báo cáo ngày (Clean Architecture) |
| `safe-refactor` | Refactor an toàn — Phase 1–5 đã xong, follow-up TD-* |

## Project memory (`_dev/`)

| Câu hỏi | Đọc |
|---------|-----|
| Lệnh CLI, CI, Husky | `_dev/commands.md` hoặc `_dev/vi/lenh-cli.md` |
| Quy trình dev/PR | `_dev/workflows.md` hoặc `_dev/vi/quy-trinh.md` |
| Commit, ESLint | `_dev/conventions.md` hoặc `_dev/vi/quy-uoc.md` |

## Tài liệu nguồn

- [PROJECT_OVERVIEW](../docs/PROJECT_OVERVIEW.md)
- [ARCHITECTURE](../docs/ARCHITECTURE.md)
- [FOLDER_STRUCTURE](../docs/FOLDER_STRUCTURE.md)
- [FRONTEND_STRUCTURE](../docs/FRONTEND_STRUCTURE.md)
- [REFACTOR_PLAN](../docs/REFACTOR_PLAN.md) — Phase 1–5 ✅
- [TECHNICAL_DEBT](../docs/TECHNICAL_DEBT.md)
- [FLOWS_AND_DOCS_MAP](../docs/FLOWS_AND_DOCS_MAP.md)
- [IMPORT_EXPORT_RECONCILE](../docs/IMPORT_EXPORT_RECONCILE.md)
- [KNOWLEDGE_BASE](../docs/KNOWLEDGE_BASE.md)
- [_dev/README.md](../_dev/README.md)

Cập nhật rules/skills khi `docs/` hoặc cấu trúc folder thay đổi.
