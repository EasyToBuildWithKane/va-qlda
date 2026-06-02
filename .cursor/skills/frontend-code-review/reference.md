# ISC Frontend Web Review — Reference

> Ví dụ React/TS giữ từ chuẩn gốc; block **Vue 3 / Inertia (VA-QLDA)** khi review repo này.

---

## LỚP A — ISC Standards (Frontend Web)

### ISC 1.2 — Đặt tên

- [ ] Biến: `camelCase` — `isLoading`, `currentUser`
- [ ] Component: `PascalCase` — `UserProfileCard.vue`
- [ ] Composables/hooks: `use` prefix — `useAuth.js`, `useNotifications.js`
- [ ] Constants: `ALL_CAPS` hoặc `export const` frozen object
- [ ] File component: `PascalCase.vue`
- [ ] File composable/util: `camelCase.js` — `useToast.js`, `notificationMeta.js`

### ISC 1.3 — Format & Style

- [ ] Không hardcode timeout, page size, base URL — const/config
- [ ] 1 composable / 1 hàm = 1 chức năng
- [ ] Không duplicate — extract composable
- [ ] Không magic string role/status — dùng const/enum mirror backend

### ISC 1.4 — Comments

- [ ] JSDoc cho composable public API phức tạp
- [ ] WHY cho debounce, race handling, workarounds
- [ ] Không TODO/FIXME treo

### ISC 1.5 — Bảo mật

- [ ] Không log token/password/OTP (`console.log`)
- [ ] Validate form trước submit (`useForm`, client rules)
- [ ] Không secret trong bundle — `VITE_*` chỉ public-safe

### ISC 2.x–4.x — API consumer

- [ ] Đúng HTTP method
- [ ] JSON API: handle `success`, `data`, `error`, `meta` khi backend bọc ISC
- [ ] `error.code` từ enum/const
- [ ] Message: `error.message` hoặc fallback generic

**Inertia:** `form.post(route(...))`, `form.errors`, flash `success`/`error`.

### ISC 5.x — Timeout & cancellation

- [ ] Axios timeout (global hoặc per-request)
- [ ] `AbortController` + cleanup `onUnmounted` cho poll/fetch
- [ ] UI retry khi timeout

---

## LỚP B — Lỗi thường gặp

### 🔴 XSS — OWASP A03

❌ React: `dangerouslySetInnerHTML`  
❌ Vue: `v-html="userComment"` không sanitize

✅ Vue:
```vue
<p>{{ userComment }}</p>
<!-- hoặc -->
<div v-html="sanitizedHtml" />  <!-- DOMPurify trước -->
```

TipTap: chỉ tin content đã qua editor schema; không `v-html` raw user markdown.

---

### 🔴 Insecure Token Storage — OWASP A02

❌ `localStorage.setItem('accessToken', token)`

✅ VA-QLDA: session cookie Laravel — auth qua `usePage().props.auth`, không lưu token FE.

---

### 🔴 CSRF — OWASP A01

✅ Inertia POST + Laravel `web` middleware + CSRF cookie tự động.

❌ Axios POST thủ công không kèm cookie/`X-XSRF-TOKEN`.

---

### 🔴 Open Redirect

❌ `router.visit(redirectFromQuery)` không validate

✅ Chỉ relative path:
```js
const r = new URLSearchParams(location.search).get('redirect') || '/dashboard';
const safe = r.startsWith('/') && !r.startsWith('//') ? r : '/dashboard';
router.visit(safe);
```

---

### 🔴 API Key trong bundle — OWASP A02

❌ `VITE_STRIPE_SECRET=sk_live_...`  
✅ Chỉ publishable key; secret ở backend.

---

### 🔴 CSP / eval

❌ `eval(userInput)`, `new Function()`, `setTimeout('...', 100)`

---

### 🟡 State management sai chỗ

❌ `useEffect` + fetch thủ công không cache (React)

❌ Vue: `onMounted` fetch + `ref` mà không cleanup, duplicate với Inertia props

✅ Vue/Inertia:
```js
// Server state → props từ controller
defineProps({ tasks: Array });
// Client-only → composable + axios với cache key rõ
```

---

### 🟡 Re-render thừa (Vue)

❌ Object/array/handler mới mỗi render trong template:
```vue
<Child :columns="[{ key: 'a' }]" @filter="(v) => filter = v" />
```

✅ `const COLUMNS = [...]` ngoài setup; `computed` / function tách file

---

### 🟡 Race / missing cleanup

❌:
```js
onMounted(async () => {
  const { data } = await axios.get(url);
  items.value = data; // unmount sau await → leak / stale
});
```

✅:
```js
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

`watch` + fetch: dùng `watchEffect` cleanup hoặc flag `let alive = true`.

---

### 🟡 Stale closure

❌ `watch`/`watchEffect` deps thiếu biến dùng trong callback

✅ Liệt kê đủ deps; hoặc functional update `count.value++` pattern

---

### 🟡 Error boundary

React: ErrorBoundary. Vue 3: `onErrorCaptured`, `app.config.errorHandler` — app shell nên có fallback (VA-QLDA: `AppDialog` + toast, tránh white screen toàn app).

---

### 🟡 Bundle size

❌ `import * as XLSX from 'xlsx'` trong `.vue`  
✅ `xlsx-js-style` chỉ trong `composables/use*Data.js`; dynamic import nếu modal nặng.

❌ Import cả thư viện lớn cho 1 hàm — import path cụ thể.

---

### 🟡 Accessibility

❌ `<div @click>` thay button  
❌ Icon button không `aria-label`  
❌ `<img>` thiếu `alt`

✅ `<button type="button" aria-label="Đóng">`, `AppIcon` + label

---

### 🟡 i18n hardcode

🔵 VA-QLDA: copy tiếng Việt trực tiếp là convention — gợi ý i18n chỉ khi module đa ngôn ngữ.

---

### 🟡 Loading / error state

❌ Render list khi `items` vẫn `[]` loading chưa xong

✅:
```vue
<div v-if="loading">...</div>
<div v-else-if="error">...</div>
<div v-else-if="!items.length">Empty</div>
<div v-else>...</div>
```

---

### 🔵 SEO meta

✅ Inertia:
```vue
<Head title="Danh sách dự án" />
```

---

### 🔵 Form — chỉ validate FE

❌ Chỉ `required` HTML, bỏ qua `form.errors` / catch axios 422

✅ `useForm` + hiển thị `form.errors.field`; toast generic cho lỗi khác

---

## Git & Commit Checklist

- [ ] Không `console.log` / `debugger`
- [ ] Không comment-out thừa
- [ ] Không credential trong source
- [ ] Đúng scope branch

## VA-QLDA patterns đúng (tham chiếu)

- Excel import/export: `.cursor/rules/import-export-reconcile.mdc`
- Page: `AppLayout` + `#header` `PageHeader`
- Permissions: `usePage().props.auth`, `project.can`
