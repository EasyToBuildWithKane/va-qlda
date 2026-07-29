---
name: add-laravel-feature
description: >-
  Adds or extends a Laravel feature in VA-Workspace following MVC or Application
  Use Case patterns. Use when creating controllers, form requests, policies,
  migrations, enums, routes, or notification hooks.
---

# Add Laravel Feature — VA-Workspace

## 1. Classify the module

| If module is… | Pattern |
|---------------|---------|
| DailyReport | Use Case + Domain (`app/Application/DailyReport/`, `app/Domain/`) |
| Project | Use Case (`app/Application/Project/`) for create/update/duplicate/archive |
| Task | Use Case (`app/Application/Task/`) for create/status/bulk |
| Blocker, Bug, Feedback, Department | Controller + FormRequest + `App\Models` |

## 2. Backend checklist

- [ ] Migration: `va_prd_*`, short index names, enums aligned
- [ ] Model: `$fillable`, `$casts`, relationships
- [ ] FormRequest: `authorize()` via policy; messages tiếng Việt
- [ ] Policy + `AuthServiceProvider` map if needed
- [ ] Controller: thin; mutations → Use Case khi module đã có
- [ ] `*Resource` for Inertia props; `can` permissions
- [ ] Route in `routes/web/{domain}.php` (static before `{param}`)
- [ ] Activity logger (Task, Blocker, …)
- [ ] `NotificationDispatcher` if user-facing event
- [ ] Feature test in `tests/Feature/` for critical paths
- [ ] Upload/public file: `Storage::disk('public')`; URL qua `PublicMediaUrl` hoặc route có policy (xem `ProjectAttachmentController::file`)

## 3. Options / constants

- Shared dropdown data → `Support/Options/*` (not raw queries in controller)
- Business constants → `config/business.php`

## 4. Bulk / import

Follow `.cursor/rules/import-export-reconcile.mdc`.

## 5. Verify

```bash
vendor/bin/pint --test
php artisan route:list --name=your.feature
php artisan test --filter=YourTest
```

See `docs/API_STRUCTURE.md`, `docs/FOLDER_STRUCTURE.md`.
