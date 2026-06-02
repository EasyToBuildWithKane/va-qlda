# ISC Backend Review — Reference (đầy đủ)

> Gốc: ISC Technical Standards. Ví dụ TypeScript/C# giữ nguyên; thêm block **Laravel (VA-QLDA)** khi áp dụng repo này.

---

## LỚP A — ISC Standards (Backend)

### ISC 1.2 — Đặt tên

- [ ] Biến thông thường: `camelCase` — `isActive`, `totalAmount`
- [ ] Biến private (C#/TS): `_camelCase` — PHP: thuộc tính private `camelCase` hoặc `snake_case` nhất quán trong class
- [ ] Hàm & Class: class `PascalCase`; method PHP/Laravel `camelCase` — `calculateTotal()`, `UserService`
- [ ] Constants: `ALL_CAPS` — `MAX_RETRY_COUNT`, `DEFAULT_TIMEOUT_MS`
- [ ] Interface: tiền tố `I` + PascalCase (C#) — PHP: không bắt buộc `I`; dùng contract/interface khi cần
- [ ] Enum values: `ALL_CAPS` hoặc `PascalCase` nhất quán — VA-QLDA: `app/Support/Enums/*` backed string

### ISC 1.3 — Format & Style

- [ ] Không hardcode — timeout, retry, page size, feature flag từ `config/` / enum
- [ ] 1 hàm = 1 chức năng, ~≤50 dòng (tách private method / Support class)
- [ ] Không code lặp — service / trait / helper
- [ ] Không magic string — dùng enum (`TaskStatus::Todo`)

### ISC 1.4 — Comments

- [ ] Public API có PHPDoc (`@param`, `@return`) khi không self-evident
- [ ] Logic phức tạp: comment **WHY**
- [ ] Không comment thừa (`// tăng i`)
- [ ] Không TODO/FIXME cũ treo

### ISC 1.5 — Bảo mật

- [ ] Không log PII: email, phone, CMND, token, password, OTP
- [ ] Validate tại entry: `FormRequest`, `authorize()`
- [ ] Không leak stack trace ra client (production)
- [ ] Không secret trong source — `.env` only

### ISC 1.6 — Nguyên tắc thiết kế

- [ ] SOLID, DRY, KISS
- [ ] DI: constructor injection, `app(Service::class)` — tránh `new` service nặng trong controller
- [ ] Ưu tiên abstraction khi module lớn (DailyReport Use Case pattern)

### ISC 2.x — API Naming

- [ ] Resource plural, lowercase: `/users`, `/projects`
- [ ] Không động từ trong path: ❌ `/getUsers` → ✅ `GET /users`
- [ ] Nesting ≤ 3 cấp
- [ ] Action: `POST /users/{id}/reset-password`
- [ ] HTTP verb đúng: GET/POST/PUT/PATCH/DELETE

**VA-QLDA routes:** `projects.tasks.store`, nested under `projects/{project}/tasks`.

### ISC 3.x — API Response Structure

```json
{ "success": true, "data": {}, "error": null, "meta": { "request_id": "...", "trace_id": "...", "timestamp": "..." } }
```

```json
{ "success": false, "data": null, "error": { "code": "USR_404", "message": "...", "retryable": false }, "meta": {} }
```

- [ ] 4 trường: `success`, `data`, `error`, `meta`
- [ ] Lỗi nghiệp vụ: HTTP 200 + `success: false` (khi API JSON theo chuẩn ISC)
- [ ] Lỗi kỹ thuật: 4xx/5xx, không 200

**Inertia (VA-QLDA):** redirect + session flash hợp lệ; JSON endpoints (`NotificationController`) nên tiến tới envelope trên nếu chuẩn hóa API.

### ISC 4.x — Error Code

- [ ] Prefix module: `AUTH_401`, `USR_404`, `ORD_409`, `SYS_500`
- [ ] Không string tùy tiện — enum/const

### ISC 5.x — Timeout

- [ ] HTTP outbound có timeout (`Http::timeout()`, Guzzle)
- [ ] Timeout từ config
- [ ] Timeout → `REQUEST_TIMEOUT`, `retryable: true` khi API JSON

---

## LỚP B — Lỗi thường gặp

### 🔴 SQL Injection — ISC 1.5 · OWASP A03

❌ Sai:
```php
DB::select("SELECT * FROM users WHERE email = '$email'");
```

✅ Đúng (Laravel):
```php
User::where('email', $email)->first();
DB::select('SELECT * FROM users WHERE email = ?', [$email]);
```

---

### 🔴 Broken Authorization / IDOR — ISC 1.5 · OWASP A01

❌ Sai:
```php
public function show(Task $task) {
    return new TaskResource($task); // không check project membership
}
```

✅ Đúng:
```php
$this->authorize('view', $project);
abort_unless($task->project_id === $project->id, 404);
```

---

### 🔴 Mass Assignment — ISC 1.5 · OWASP A03

❌ Sai:
```php
$task->update($request->all());
```

✅ Đúng:
```php
$task->update($request->validated()); // FormRequest whitelist
```

---

### 🔴 SSRF — ISC 1.5 · OWASP A10

❌ Sai: `Http::get($request->input('url'))` không validate host.

✅ Đúng: allowlist hostname + chỉ `https`.

---

### 🔴 Secret Hardcode — ISC 1.5 · OWASP A02

❌ Sai: `config('app.key')` thay bằng chuỗi cố định trong class.

✅ Đúng: `env()` / `config()` từ `.env`, `.env` trong `.gitignore`.

---

### 🔴 CSRF — ISC 1.5 · OWASP A01

Laravel web middleware `@csrf` / `VerifyCsrfToken` — state-changing POST phải qua web middleware.

---

### 🟡 N+1 — ISC 1.6

❌ Sai: load tasks rồi loop `$task->assignee` chưa eager load.

✅ Đúng: `Task::with(['assignee', 'project'])->...`

---

### 🟡 Missing Index — ISC 1.6

Migration: index cho cột filter/sort thường xuyên (`recipient_account_id`, `created_at`).

---

### 🟡 Transaction — ISC 1.6

❌ Sai: nhiều `save()` không bọc transaction.

✅ Đúng:
```php
DB::transaction(function () use ($rows) {
    foreach ($rows as $row) { /* ... */ }
});
```

---

### 🟡 Race Condition — ISC 1.6

✅ Đúng: `decrement()` atomic, `lockForUpdate()`, hoặc optimistic lock.

---

### 🟡 Idempotency — ISC 1.6

POST tạo resource quan trọng: `Idempotency-Key` header + cache kết quả.

---

### 🟡 Rate Limiting — ISC 1.5

`Route::middleware('throttle:5,1')` trên login/OTP.

---

### 🟡 Log PII — ISC 1.5

❌ `Log::info('login', ['password' => $password]);`

✅ `Log::info('login failed', ['user_id' => $id]);`

---

### 🟡 Circuit Breaker — ISC 5.x

External API: timeout + fallback, không chờ vô hạn.

---

### 🔵 Missing Pagination

❌ `Model::all()` trả list lớn.

✅ `paginate()` / cursor + `per_page` cap (vd. max 50).

---

### 🔵 Error Leak — ISC 1.5 · 3.x

❌ `return response()->json(['stack' => $e->getTraceAsString()]);`

✅ `report($e);` + message generic; chi tiết chỉ log server.

---

## Git & Commit Checklist

- [ ] Không `dd()` / `dump()` / debug
- [ ] Không code commented-out thừa
- [ ] Không credential trong source
- [ ] File đúng scope branch/task
