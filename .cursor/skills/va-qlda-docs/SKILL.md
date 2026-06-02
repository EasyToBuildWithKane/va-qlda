---
name: va-qlda-docs
description: >-
  Navigates VA-QLDA technical documentation in docs/ and operational memory in
  _dev/ for architecture, routes, frontend structure, refactor status, and debt.
  Use when onboarding, planning features, answering "how does X work", or before
  large changes to align with documented conventions.
---

# VA-QLDA Documentation Navigator

## When to use

- User asks how the system works, where code lives, or what to build next.
- Planning refactor follow-up, new module, or cross-cutting feature.
- Need route names, table names, layer responsibilities, or CLI/CI commands.

## Doc map (read selectively)

| File | Use for |
|------|---------|
| `docs/PROJECT_OVERVIEW.md` | Modules, flows, roles, stack |
| `docs/ARCHITECTURE.md` | Layers, coupling, current vs target |
| `docs/FOLDER_STRUCTURE.md` | Where files live (post-refactor) |
| `docs/FRONTEND_STRUCTURE.md` | Pages, modules/, shared/, composables, Pinia |
| `docs/API_STRUCTURE.md` | All web routes, Inertia vs JSON |
| `docs/DATABASE_STRUCTURE.md` | Tables, columns, ERD |
| `docs/REFACTOR_PLAN.md` | Phase 1–5 ✅ complete; follow-up items |
| `docs/TECHNICAL_DEBT.md` | Open issues (TD-002, TD-010, …) |
| `docs/NEXT_STEPS.md` | Roadmap, quick wins |

## Operational memory (`_dev/`)

| Question | Read |
|----------|------|
| CLI commands, Husky, Playwright | `_dev/commands.md` |
| Dev / PR / hotfix workflow | `_dev/workflows.md` |
| Commit format, ESLint | `_dev/conventions.md` |
| GitHub Actions | `_dev/ci-cd.md` |
| Giải thích tiếng Việt | `_dev/vi/` |

## Workflow

1. Identify module (Project, DailyReport, Blocker, …).
2. Open relevant doc + grep codebase.
3. Match **existing** pattern:
   - DailyReport → Use Case + Domain
   - Project/Task mutations → Application Use Case
   - Blocker/Bug → MVC
4. Import/export → `.cursor/rules/import-export-reconcile.mdc`.

## Project facts (quick)

- Auth: `SystemAccount`, roles `admin|lead|member|viewer`.
- Transport: Inertia primary; JSON for notifications only.
- Frontend: `modules/project/`, `shared/ui/`, Pinia stores.
- Refactor Phase 1–5: **done** (2026-06-03).

## Output

Summarize with links to doc paths and concrete file paths in the repo.
