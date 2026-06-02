---
name: add-laravel-feature
description: >-
  Adds or extends a Laravel feature in VA-QLDA following MVC or DailyReport
  Clean Architecture patterns. Use when creating controllers, form requests,
  policies, migrations, enums, routes, or notification hooks.
---

# Add Laravel Feature — VA-QLDA

## 1. Classify the module

| If module is… | Pattern |
|---------------|---------|
| DailyReport | Use Case in `app/Application/DailyReport/` |
| Project, Task, Blocker, Bug, … | Controller + FormRequest + `App\Models` |

## 2. Backend checklist

- [ ] Migration: `va_prd_*`, short index names, enums aligned
- [ ] Model: `$fillable`, `$casts`, relationships, enum casts
- [ ] `Store*Request` / `Update*Request`: `authorize()` via policy
- [ ] Policy method + map in `AuthServiceProvider` if needed
- [ ] Controller: thin; `back()->with('success', '...')` Vietnamese
- [ ] `*Resource` for Inertia props if list/detail heavy
- [ ] Route in `routes/web.php` (static routes before `{param}`)
- [ ] Activity logger if entity has audit trail (Task, Blocker, …)
- [ ] `NotificationDispatcher` / `NotificationService` if user-facing event

## 3. Bulk / import

Follow `.cursor/rules/import-export-reconcile.mdc` — never loop `router.post` per row from browser.

## 4. Verify

```bash
php artisan route:list --name=your.feature
php artisan migrate --pretend
```

## Anti-patterns

- Business logic only in Blade or Resource
- New God static helpers in `Options.php` without need
- Skipping policy on mutating routes

See `docs/API_STRUCTURE.md` for route naming examples.
