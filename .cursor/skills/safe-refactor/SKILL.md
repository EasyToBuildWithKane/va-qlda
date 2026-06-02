---
name: safe-refactor
description: >-
  Plans or executes safe refactors in VA-QLDA per REFACTOR_PLAN and TECHNICAL_DEBT
  without mixing feature work. Use when reorganizing folders, extracting use cases,
  or addressing TD-001–TD-009.
---

# Safe Refactor — VA-QLDA

## Rules (from docs/REFACTOR_PLAN.md)

1. **No feature + refactor** in the same PR.
2. **Backward compatible** each step.
3. **Small commits** — easy rollback.
4. Do **not** start Phase 2+ folder moves unless user explicitly approves.

## Before coding

1. Read `docs/TECHNICAL_DEBT.md` for ID (TD-001…).
2. Read matching section in `docs/REFACTOR_PLAN.md`.
3. List files to move/import updates; grep all references.

## Allowed quick wins (low risk)

- Constants → `config/business.php` / `resources/js/constants/`
- Enum `options()` / `label()` completeness
- Feature tests in `tests/Feature/` for critical paths

## Deferred without approval

- Move `Components/Project/*` → `modules/project/`
- Pinia stores
- REST `api.php` layer
- Extract Use Cases for Project/Task

## Frontend move checklist (when approved)

- [ ] Move file
- [ ] Update all `@/` imports
- [ ] `npm run build` passes
- [ ] Smoke test affected pages

## Output format for plans

```markdown
## Goal
## Scope (files)
## Out of scope
## Steps (ordered)
## Risk / rollback
```
