# Safe Refactor — VA-QLDA

Lập kế hoạch hoặc thực thi refactor an toàn theo REFACTOR_PLAN và TECHNICAL_DEBT, không mix feature work.

## Rules (từ docs/REFACTOR_PLAN.md)

1. **Không feature + refactor** trong cùng PR.
2. **Backward compatible** từng bước.
3. **Small commits** — dễ rollback.
4. Không bắt đầu Phase 2+ folder moves trừ khi user duyệt tường minh.

## Trước khi code

1. Đọc `docs/TECHNICAL_DEBT.md` để lấy ID (TD-001…).
2. Đọc phần tương ứng trong `docs/REFACTOR_PLAN.md`.
3. Liệt kê files cần move/import updates; grep tất cả references.

## Quick wins được phép (rủi ro thấp)

- Constants → `config/business.php` / `resources/js/constants/`
- Enum `options()` / `label()` completeness
- Feature tests trong `tests/Feature/` cho critical paths

## Bị defer nếu không có approval

- Move `Components/Project/*` → `modules/project/`
- Pinia stores
- REST `api.php` layer
- Extract Use Cases cho Project/Task

## Frontend move checklist (khi được duyệt)

- [ ] Move file
- [ ] Update tất cả `@/` imports
- [ ] `npm run build` passes
- [ ] Smoke test affected pages

## Output format cho plans

```markdown
## Goal
## Scope (files)
## Out of scope
## Steps (ordered)
## Risk / rollback
```
