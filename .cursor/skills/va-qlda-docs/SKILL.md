---
name: va-qlda-docs
description: >-
  Navigates VA-QLDA technical documentation in docs/ for architecture,
  API routes, database schema, frontend structure, refactor plan, and debt.
  Use when onboarding, planning features, answering "how does X work", or
  before large changes to align with documented conventions.
---

# VA-QLDA Documentation Navigator

## When to use

- User asks how the system works, where code lives, or what to build next.
- Planning refactor, new module, or cross-cutting feature.
- Need route names, table names, or layer responsibilities.

## Doc map (read selectively)

| File | Use for |
|------|---------|
| `docs/PROJECT_OVERVIEW.md` | Modules, flows, roles, stack |
| `docs/ARCHITECTURE.md` | Layers, coupling, target architecture |
| `docs/FOLDER_STRUCTURE.md` | Where files live / should move |
| `docs/FRONTEND_STRUCTURE.md` | Pages, components, composables, UI patterns |
| `docs/API_STRUCTURE.md` | All web routes, Inertia vs JSON |
| `docs/DATABASE_STRUCTURE.md` | Tables, columns, ERD |
| `docs/REFACTOR_PLAN.md` | Phased refactor — **do not execute without approval** |
| `docs/TECHNICAL_DEBT.md` | Known issues (TD-001…) |
| `docs/NEXT_STEPS.md` | Roadmap, quick wins |

## Workflow

1. Identify module (Project, DailyReport, Blocker, …).
2. Open relevant doc sections + grep codebase (`routes/web.php`, `Pages/`, `Controllers/`).
3. Match **existing** pattern (DailyReport = Use Case; Project = MVC).
4. If change touches import/export → also read `.cursor/rules/import-export-reconcile.mdc`.

## Project facts (quick)

- Auth: `SystemAccount`, roles `admin|lead|member|viewer`.
- Primary transport: Inertia, not REST `api.php`.
- Notifications: `app_notifications` + drawer (see codebase, not only roadmap in NEXT_STEPS).

## Output

Summarize findings with links to doc paths and concrete file paths in the repo.
