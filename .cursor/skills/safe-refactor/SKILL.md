---
name: safe-refactor
description: >-
  Plans or executes safe refactors in VA-Workspace per REFACTOR_PLAN and TECHNICAL_DEBT
  without mixing feature work. Phase 1-5 complete; use for follow-up TD items.
---

# Safe Refactor — VA-Workspace

## Status (2026-06-03)

**Refactor Phase 1–5: ✅ COMPLETE**

- Phase 1: constants, enums, feature tests
- Phase 2: `modules/project/`, `shared/ui/`, removed `Components/Project/`
- Phase 3: Project/Task Use Cases, Options services, Pinia
- Phase 4: shared UI library (form/, EmptyState, …)
- Phase 5: lazy routes, Vite chunks, Options cache

## Rules (always)

1. **No feature + refactor** in the same PR.
2. **Backward compatible** each step.
3. **Small commits** — Conventional Commits, Husky hooks pass.
4. Update `docs/` + `_dev/` when operational knowledge changes.

## Open follow-ups (from TECHNICAL_DEBT)

| ID | Task |
|----|------|
| TD-002 | Extract heavy queries from ProjectController/TaskController |
| TD-010 | DailyReport `project_id` legacy cleanup |
| TD-007 | Frontend `services/http.js` (optional) |
| — | N+1 / index audit (REFACTOR_PLAN Phase 5.4) |

## Frontend move checklist (if moving more files)

- [ ] Move file to `modules/` or `shared/`
- [ ] Grep + update all `@/` imports
- [ ] `npm run lint` + `npm run build`
- [ ] Playwright E2E / smoke test affected pages

## Output format for plans

```markdown
## Goal
## Scope (files)
## Out of scope
## Steps (ordered)
## Risk / rollback
```

See `docs/REFACTOR_PLAN.md`, `docs/TECHNICAL_DEBT.md`.
