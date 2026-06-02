# Quy ước code & commit — hướng dẫn tiếng Việt

**File gốc:** [`../conventions.md`](../conventions.md)

Tiêu chuẩn được tool và rule dự án enforce.

---

## Format commit (commitlint)

Config: `commitlint.config.js` — mở rộng `@commitlint/config-conventional`.

```
type(scope): mo ta ngan
```

| Quy tắc | Giá trị |
|---------|---------|
| **Types** | `feat` · `fix` · `docs` · `style` · `refactor` · `perf` · `test` · `build` · `ci` · `chore` · `revert` |
| **Header tối đa** | 72 ký tự |
| **Dòng body tối đa** | 100 ký tự |
| **Subject case** | Không dùng Sentence case, Start Case, PascalCase, HOẶC IN HOA |

**Ví dụ:**

```
feat(auth): add Google OAuth login
fix(api): handle null response from payment gateway
chore(deps): upgrade Vue to 3.5.x
refactor(project): extract useSprintData composable
test(e2e): add login failure scenario
```

**Kiểm tra thủ công:**

```bash
echo "feat: test message" | npm run commitlint
```

**Enforce:** hook Husky `commit-msg` chạy commitlint mỗi lần commit.

> **Ghi chú:** Message commit thường viết tiếng Anh (Conventional Commits). UI và validation message trong app viết **tiếng Việt**.

---

## Đặt tên nhánh

```
feat/mo-ta-ngan
fix/ten-loi-hoac-mo-ta
chore/cong-viec
refactor/ten-module
docs/cap-nhat-readme
```

Chữ thường, gạch ngang. Prefix khớp với type commit khi có thể.

---

## Quy ước Vue

Dự án dùng **JavaScript** (không TypeScript) cho frontend.

| Quy tắc | Quy ước |
|---------|---------|
| Một component / file | Một file `.vue` |
| Tên file | PascalCase — `ProjectCard.vue`, `PageHeader.vue` |
| API | Composition API + `<script setup>` |
| Props | `defineProps({ ... })` — validate runtime |
| Import | `@/` → `resources/js/` |
| Pages | Mỏng, trong `Pages/{Domain}/`, bọc `AppLayout` |
| Logic | Tách ra `composables/use*.js` |
| Icon | `<AppIcon name="task" />` |
| Dialog | `useDialog`, `useToast` — không `alert()` |

**Cấu trúc thư mục:**

```
resources/js/
├── Pages/{Domain}/
├── Components/Ui/
├── Components/Project/
├── composables/
└── Layouts/AppLayout.vue
```

**Copy & UX:** text UI, flash, validation — **tiếng Việt**.  
Màu brand: `#9A0036` (Tailwind `brand`).

---

## Quy ước PHP / Laravel

| Quy tắc | Quy ước |
|---------|---------|
| Kiến trúc | MVC (Project, Task, Blocker…); Clean Architecture (DailyReport) |
| Phân quyền | `$this->authorize('manage', $project)` |
| Validation | FormRequest + `messages()` tiếng Việt |
| Enum | `app/Support/Enums/` — string enum + `values()`, `label()` |
| Bảng DB | Prefix `va_prd_` |
| Style PHP | Laravel Pint (`composer format`) |
| Phân tích tĩnh | PHPStan (`composer analyse`) |

---

## Rule ESLint đang áp dụng

Config: `eslint.config.js` (ESLint 9 flat config).

**Mở rộng:**

- `@eslint/js` recommended
- `eslint-plugin-vue` flat/recommended

**Phạm vi:** `resources/js/**/*.{js,vue}`

**Bỏ qua:** `public/build/**`, `node_modules/**`, `vendor/**`

**Rule tùy chỉnh:**

| Rule | Cấu hình | Ý nghĩa |
|------|----------|---------|
| `vue/multi-word-component-names` | `off` | Cho phép tên 1 từ (`Modal.vue`) |
| `no-unused-vars` | `warn`, `argsIgnorePattern: '^_'` | Biến không dùng → cảnh báo; arg prefix `_` được bỏ qua |

**CLI:** `npm run lint` fail nếu có **bất kỳ warning nào** (`--max-warnings=0`).

**Pre-commit:** lint-staged chạy `eslint --fix --max-warnings=0`.

---

## Module nhập · xuất · đối soát

Theo `.cursor/rules/import-export-reconcile.mdc`:

- Một `*DataModal.vue` — 3 tab: nhập · xuất · đối soát
- Logic Excel trong `use*Data.js` — **cấm** import `xlsx` trong `.vue`
- Tham chiếu: Risk/Blocker (`useRiskImport.js`, `RiskImportModal.vue`)
