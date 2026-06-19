# Safe Refactor — VA-QLDA

## Status

**Phase 1–5: ✅ Complete (2026-06-03)**

## Rules

1. Không mix feature + refactor trong cùng PR
2. Commit nhỏ, Husky pass
3. Cập nhật `docs/` + `_dev/` khi đổi quy trình

## Follow-up mở

- TD-002: tách query khỏi ProjectController/TaskController
- TD-010: DailyReport project_id legacy
- N+1 / database indexes

## Checklist move file

- [ ] Update imports
- [ ] `npm run lint` + `npm run build`
- [ ] E2E nếu UI đổi

See `docs/TECHNICAL_DEBT.md`, `docs/REFACTOR_PLAN.md`
