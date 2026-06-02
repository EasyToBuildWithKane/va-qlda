# Daily Report Domain — VA-QLDA

Implement hoặc fix Daily Report features dùng Clean Architecture (Application Use Cases, Domain models).

## Boundaries

```
app/Application/DailyReport/   ← Use Cases (inject vào controller)
app/Domain/DailyReport/        ← Models, ScoringService, Exceptions
app/Http/Controllers/DailyReport/
app/Policies/DailyReportPolicy.php
```

Không đặt Daily Report business rules trong `App\Models\Project` hoặc generic controllers.

## Flow

```
Member: Today → store/update → submit (SUBMITTED)
Lead/Admin: Review → score (REVIEWED + grade) | reject → DRAFT
```

## Use Case pattern

Controller validates via FormRequest → gọi single Use Case → catch `DailyReportException` → redirect + flash.

## Routes (thứ tự quan trọng)

`/daily-reports/review` trước `/daily-reports/{report}`.

## Spatie activity

`DailyReport` dùng `LogsActivity` — giữ logging nhất quán khi thêm fields.

## Reference

- `docs/PROJECT_OVERVIEW.md` §3.2
- `docs/ARCHITECTURE.md` §1
- Existing: `SubmitDailyReportUseCase`, `ScoreReportUseCase`, `RejectReportUseCase`
