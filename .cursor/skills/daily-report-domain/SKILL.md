---
name: daily-report-domain
description: >-
  Implements or fixes Daily Report features using VA-QLDA Clean Architecture
  (Application Use Cases, Domain models). Use for báo cáo ngày, submit,
  review, score, reject, or DailyReportPolicy.
---

# Daily Report Domain — VA-QLDA

## Boundaries

```
app/Application/DailyReport/   ← Use Cases (inject vào controller)
app/Domain/DailyReport/        ← Models, ScoringService, Exceptions
app/Http/Controllers/DailyReport/
app/Policies/DailyReportPolicy.php
```

**Do not** put Daily Report business rules in `App\Models\Project` or generic controllers.

## Flow

```
Member: Today → store/update → submit (SUBMITTED)
Lead/Admin: Review → score (REVIEWED + grade) | reject → DRAFT
```

## Use Case pattern

Controller validates via FormRequest → calls single Use Case → catches `DailyReportException` → redirect + flash.

## Routes (order matters)

`/daily-reports/review` before `/daily-reports/{report}`.

## Spatie activity

`DailyReport` uses `LogsActivity` — keep logging consistent when adding fields.

## Reference

- `docs/DAILY_REPORT.md` — module doc (routes, RBAC, task sync, frontend)
- `docs/DAILY_REPORT_PROJECTS.md` — `projects` JSON vs `project_id`
- `docs/PROJECT_OVERVIEW.md` §3.2
- `docs/ARCHITECTURE.md` §1
- Existing: `SubmitDailyReportUseCase`, `ScoreReportUseCase`, `RejectReportUseCase`
