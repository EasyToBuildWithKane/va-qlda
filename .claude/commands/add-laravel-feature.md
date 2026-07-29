# Add Laravel Feature — VA-Workspace

Thêm hoặc mở rộng Laravel feature theo MVC hoặc DailyReport Clean Architecture patterns.

## 1. Phân loại module

| Nếu module là… | Pattern |
|----------------|---------|
| DailyReport | Use Case trong `app/Application/DailyReport/` |
| Project, Task, Blocker, Bug, … | Controller + FormRequest + `App\Models` |

## 2. Backend checklist

- [ ] Migration: `va_prd_*`, short index names, enums aligned
- [ ] Model: `$fillable`, `$casts`, relationships, enum casts
- [ ] `Store*Request` / `Update*Request`: `authorize()` via policy
- [ ] Policy method + map trong `AuthServiceProvider` nếu cần
- [ ] Controller: thin; `back()->with('success', '...')` tiếng Việt
- [ ] `*Resource` cho Inertia props nếu list/detail nặng
- [ ] Route trong `routes/web/{domain}.php` (static routes trước `{param}`)
- [ ] Activity logger nếu entity có audit trail (Task, Blocker, …)
- [ ] `NotificationDispatcher` / `NotificationService` nếu có user-facing event

## 3. Bulk / import

Theo phần Nhập/Xuất/Đối soát trong `CLAUDE.md` — không loop `router.post` từng record từ browser.

## 4. Verify

```bash
php artisan route:list --name=your.feature
php artisan migrate --pretend
```

## Anti-patterns

- Business logic chỉ trong Blade hoặc Resource
- Thêm static helper vào `Options.php` khi không cần thiết
- Bỏ qua policy trên mutating routes

Xem `docs/API_STRUCTURE.md` để tham khảo route naming.
