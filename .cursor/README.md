# Cursor — VA-QLDA

Cấu hình AI cho repo **VAschools QLDA**, đồng bộ với [`docs/`](../docs/).

## Rules (`.cursor/rules/`)

| File | Áp dụng |
|------|---------|
| `va-qlda-core.mdc` | Luôn — stack, auth, kiến trúc tổng |
| `laravel-backend.mdc` | `app/`, `routes/`, `database/` PHP |
| `vue-inertia-frontend.mdc` | `resources/js/**` |
| `database-schema.mdc` | Migrations & models |
| `import-export-reconcile.mdc` | Nhập / xuất / đối soát Excel |
| `backend-code-review-isc.mdc` | Review backend PHP theo ISC + bảo mật |
| `frontend-code-review-isc.mdc` | Review frontend Vue/Inertia theo ISC + bảo mật |

## Skills (`.cursor/skills/`)

| Skill | Khi dùng |
|-------|----------|
| `backend-code-review` | Review `git diff --staged` theo ISC + OWASP (BE) |
| `frontend-code-review` | Review `git diff --staged` theo ISC + OWASP (FE Vue/Inertia) |
| `va-qlda-docs` | Đọc / tra cứu tài liệu `docs/` |
| `add-laravel-feature` | Thêm API/backend feature |
| `add-vue-page` | Thêm page / component Inertia |
| `daily-report-domain` | Module báo cáo ngày (Clean Architecture) |
| `safe-refactor` | Refactor theo REFACTOR_PLAN |

**Review:** *«review backend staged»* → `backend-code-review` · *«review frontend staged»* → `frontend-code-review`

## Tài liệu nguồn

- [PROJECT_OVERVIEW](../docs/PROJECT_OVERVIEW.md)
- [ARCHITECTURE](../docs/ARCHITECTURE.md)
- [FRONTEND_STRUCTURE](../docs/FRONTEND_STRUCTURE.md)
- [API_STRUCTURE](../docs/API_STRUCTURE.md)
- [DATABASE_STRUCTURE](../docs/DATABASE_STRUCTURE.md)
- [REFACTOR_PLAN](../docs/REFACTOR_PLAN.md)
- [TECHNICAL_DEBT](../docs/TECHNICAL_DEBT.md)
- [NEXT_STEPS](../docs/NEXT_STEPS.md)

Cập nhật rules/skills khi `docs/` thay đổi kiến trúc hoặc quy ước mới.
