# Frontend Code Review — ISC + Security/Performance

Review staged hoặc unstaged frontend code (Vue 3, Inertia, JavaScript) theo ISC Technical Standards + web security (XSS, token storage, CSRF, race conditions, re-renders).

## 1. Lấy diff

```bash
git diff --staged
git status
```

- Có staged → review `--staged` only.
- Không staged → `git diff HEAD`, báo user.
- Diff rỗng → dừng.

## 2. Phạm vi

`resources/js/**` (`.vue`, `.js`), `resources/css/**`. Bỏ qua `public/build`, vendor.

**Stack:** Vue 3 Composition API + Inertia.

## 3. Hai lớp review

| Lớp | Nội dung |
|-----|----------|
| **A — ISC FE** | Đặt tên, style, comment, bảo mật, API consumer, timeout/abort |
| **B — Lỗi thường gặp** | XSS, token storage, CSRF, open redirect, secrets in bundle, CSP/eval, state, re-render, race/cleanup, a11y, loading/error, form validation |

**Inertia:** Không bắt buộc envelope `success/data/error/meta` trên mọi page — áp dụng cho axios JSON; form errors qua `form.errors` + flash.

## 4. Format kết quả (bắt buộc)

### Tổng quan
- Số file, loại thay đổi
- **Pass** / **Cần cải thiện** / **Có vấn đề nghiêm trọng**

### Vấn đề phát hiện

| Mức | Ký hiệu |
|-----|--------|
| Critical | 🔴 |
| Warning | 🟡 |
| Suggestion | 🔵 |

Mỗi issue: (1) file + dòng từ diff (2) ISC/OWASP (3) fix gợi ý Vue/Inertia.

### Điểm tốt

### Git & commit checklist
- [ ] Không `console.log` / `debugger`
- [ ] Không code comment-out thừa
- [ ] Không credential / API key trong source
- [ ] Scope đúng task/branch

## 5. Hành vi

- Chỉ review — không sửa trừ khi user yêu cầu.
- Không Pass nếu còn 🔴 chưa nêu.
- Ưu tiên vi phạm **trong diff**.

---

## Reference — ISC Frontend (đầy đủ)

### LỚP A — ISC Standards

**ISC 1.2 — Đặt tên**
- Biến: `camelCase`; Component: `PascalCase.vue`; Composables: `use` prefix — `useAuth.js`
- Constants: `ALL_CAPS` hoặc `export const` frozen object

**ISC 1.3 — Format**
- Không hardcode timeout, page size, base URL — const/config
- 1 composable / 1 hàm = 1 chức năng
- Không magic string role/status — dùng const/enum mirror backend

**ISC 1.5 — Bảo mật**
- Không log token/password/OTP qua `console.log`
- Không secret trong bundle — `VITE_*` chỉ public-safe

**ISC 5.x — Timeout & cancellation**
- Axios timeout (global hoặc per-request)
- `AbortController` + cleanup `onUnmounted` cho poll/fetch

### LỚP B — Lỗi thường gặp

**🔴 XSS — OWASP A03**
```vue
<!-- ❌ -->
<div v-html="userComment" />
<!-- ✅ -->
<p>{{ userComment }}</p>
<!-- hoặc DOMPurify trước v-html -->
```
TipTap: không `v-html` raw user markdown.

**🔴 Insecure Token Storage — OWASP A02**
```
// ❌ localStorage.setItem('accessToken', token)
// ✅ VA-QLDA: session cookie Laravel — auth qua usePage().props.auth
```

**🔴 CSRF — OWASP A01**
- Inertia POST + Laravel `web` middleware + CSRF cookie tự động.
- Axios POST thủ công phải kèm `X-XSRF-TOKEN`.

**🔴 Open Redirect**
```js
// ❌ router.visit(redirectFromQuery) không validate
// ✅
const r = new URLSearchParams(location.search).get('redirect') || '/dashboard';
const safe = r.startsWith('/') && !r.startsWith('//') ? r : '/dashboard';
router.visit(safe);
```

**🔴 API Key trong bundle — OWASP A02**
- `VITE_STRIPE_SECRET=sk_live_...` → ❌; chỉ publishable key; secret ở backend.

**🟡 State management sai chỗ**
```js
// ✅ Server state → props từ controller
defineProps({ tasks: Array });
// ✅ Client-only → composable + axios với cache key rõ
```

**🟡 Re-render thừa (Vue)**
```vue
<!-- ❌ Object mới mỗi render -->
<Child :columns="[{ key: 'a' }]" />
<!-- ✅ -->
<!-- const COLUMNS = [...] ngoài setup; hoặc computed -->
```

**🟡 Race / missing cleanup**
```js
// ✅
let controller;
onMounted(async () => {
  controller = new AbortController();
  try {
    const { data } = await axios.get(url, { signal: controller.signal });
    items.value = data;
  } catch (e) { if (e.name !== 'CanceledError') throw e; }
});
onUnmounted(() => controller?.abort());
```

**🟡 Bundle size**
- `import * as XLSX from 'xlsx'` trong `.vue` → ❌
- `xlsx-js-style` chỉ trong `composables/use*Data.js`; dynamic import nếu modal nặng.

**🟡 Accessibility**
- `<div @click>` thay button → ❌
- Icon button không `aria-label` → ❌
- `<button type="button" aria-label="Đóng">`, `AppIcon` + label → ✅

**🟡 Loading / error state**
```vue
<div v-if="loading">...</div>
<div v-else-if="error">...</div>
<div v-else-if="!items.length">Trống</div>
<div v-else><!-- content --></div>
```

**🔵 SEO meta**
```vue
<Head title="Danh sách dự án" />
```

**🔵 Form — chỉ validate FE**
- Hiển thị `form.errors.field`; toast generic cho lỗi khác.
- Không chỉ dùng `required` HTML.
