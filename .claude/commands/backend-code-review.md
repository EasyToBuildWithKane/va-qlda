# Backend Code Review — ISC + Security/Performance

Review staged hoặc unstaged backend code theo ISC Technical Standards + security/performance (SQL injection, IDOR, mass assignment, N+1, transactions).

## 1. Lấy diff

Chạy song song:

```bash
git diff --staged
git status
```

- Có staged → review **chỉ** `--staged`.
- Không staged → chạy `git diff HEAD`, báo user đang review toàn bộ thay đổi chưa stage.
- Diff rỗng → dừng, báo không có gì để review.

## 2. Phạm vi file

Ưu tiên: `app/`, `routes/`, `database/migrations/`, `tests/`. Bỏ qua lockfile/vendor trừ khi diff có secrets.

## 3. Review theo hai lớp

| Lớp | Nội dung |
|-----|----------|
| **A — ISC** | Đặt tên, format, comment, bảo mật, SOLID/DRY/KISS/DI, API naming, response, error code, timeout |
| **B — Lỗi thường gặp** | SQLi, IDOR, mass assignment, SSRF, secrets, CSRF, N+1, index, transaction, race, idempotency, rate limit, log PII, circuit breaker, pagination, error leak |

**VA-QLDA:** Inertia web chủ đạo — ISC 3.x JSON envelope áp dụng cho endpoint JSON (`NotificationController`); Inertia redirect + `with('success'|'error')` là hợp lệ.

## 4. Format kết quả (bắt buộc)

### Tổng quan
- Số file, loại thay đổi (feat/fix/refactor…)
- **Pass** / **Cần cải thiện** / **Có vấn đề nghiêm trọng**

### Vấn đề phát hiện

| Mức | Ký hiệu | Ý nghĩa |
|-----|--------|---------|
| Critical | 🔴 | Bảo mật, data loss, secret hardcode |
| Warning | 🟡 | Convention, error handling, performance |
| Suggestion | 🔵 | Readability, chất lượng |

Mỗi issue gồm: (1) file + dòng từ diff (2) tiêu chuẩn ISC/OWASP/Laravel (3) gợi ý fix có code snippet.

### Điểm tốt

### Git & commit checklist
- [ ] Không `dd()` / `dump()` / debug sót
- [ ] Không code comment-out thừa
- [ ] Không credential trong source
- [ ] Scope đúng branch/task

## 5. Hành vi

- **Chỉ review** — không sửa code trừ khi user yêu cầu fix.
- Không kết luận Pass nếu còn 🔴 chưa giải thích.
- Ưu tiên vấn đề trong **diff**, không lecture toàn repo.

---

## Reference — ISC Backend (đầy đủ)

### LỚP A — ISC Standards

**ISC 1.2 — Đặt tên**
- Biến: `camelCase`; class `PascalCase`; method PHP `camelCase`; const `ALL_CAPS`
- Enum values: `ALL_CAPS` hoặc `PascalCase` nhất quán — VA-QLDA: `app/Support/Enums/*` backed string

**ISC 1.3 — Format**
- Không hardcode — timeout, retry, page size từ `config/` / enum
- 1 hàm = 1 chức năng, ~≤50 dòng
- Không magic string — dùng enum (`TaskStatus::Todo`)

**ISC 1.5 — Bảo mật**
- Không log PII: email, phone, CMND, token, password, OTP
- Validate tại entry: `FormRequest`, `authorize()`
- Không secret trong source — `.env` only

**ISC 2.x — API Naming**
- Resource plural, lowercase: `/users`, `/projects`
- Không động từ trong path: ❌ `/getUsers` → ✅ `GET /users`

**ISC 3.x — API Response**
```json
{ "success": true, "data": {}, "error": null, "meta": { "request_id": "...", "timestamp": "..." } }
```

### LỚP B — Lỗi thường gặp

**🔴 SQL Injection — OWASP A03**
```php
// ❌ Sai
DB::select("SELECT * FROM users WHERE email = '$email'");
// ✅ Đúng
User::where('email', $email)->first();
```

**🔴 IDOR — OWASP A01**
```php
// ❌ Sai — không check project membership
public function show(Task $task) { return new TaskResource($task); }
// ✅ Đúng
$this->authorize('view', $project);
abort_unless($task->project_id === $project->id, 404);
```

**🔴 Mass Assignment — OWASP A03**
```php
// ❌ $task->update($request->all());
// ✅ $task->update($request->validated());
```

**🔴 Secret Hardcode — OWASP A02**
- `.env` only; không hardcode API keys, tokens trong source.

**🔴 CSRF — OWASP A01**
- State-changing POST phải qua web middleware + CSRF token.

**🟡 N+1 — ISC 1.6**
```php
// ❌ loop $task->assignee chưa eager load
// ✅ Task::with(['assignee', 'project'])->...
```

**🟡 Transaction — ISC 1.6**
```php
DB::transaction(function () use ($rows) {
    foreach ($rows as $row) { /* ... */ }
});
```

**🟡 Rate Limiting — ISC 1.5**
- `Route::middleware('throttle:5,1')` trên login/OTP.

**🟡 Log PII — ISC 1.5**
```php
// ❌ Log::info('login', ['password' => $password]);
// ✅ Log::info('login failed', ['user_id' => $id]);
```

**🔵 Missing Pagination**
```php
// ❌ Model::all() — ✅ paginate() / cursor + per_page cap (max 50)
```

**🔵 Error Leak — ISC 1.5**
```php
// ❌ return response()->json(['stack' => $e->getTraceAsString()]);
// ✅ report($e); + message generic
```
