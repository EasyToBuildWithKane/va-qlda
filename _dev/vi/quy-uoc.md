# Quy ước code & commit — hướng dẫn tiếng Việt

**File gốc:** [`../conventions.md`](../conventions.md)

---

## Format commit (commitlint)

Config: `commitlint.config.js` — `@commitlint/config-conventional`.

```
type(scope): mo ta ngan
```

| Quy tắc | Giá trị |
|---------|---------|
| **Types** | `feat` · `fix` · `docs` · `style` · `refactor` · `perf` · `test` · `build` · `ci` · `chore` · `revert` |
| Header | ≤ 72 ký tự |
| Body line | ≤ 100 ký tự |
| Subject | Không Sentence case / PascalCase / ALL CAPS |

**Ví dụ:**

```
feat(auth): add Google OAuth login
fix(kb): sync TOC sidebar on mobile
docs(flows): update FLOWS_AND_DOCS_MAP
```

```bash
echo "feat: test" | npm run commitlint
```

Hook `commit-msg` enforce mỗi commit.

**Cursor gõ "Updates" / để trống:** `prepare-commit-msg` + `fix-commit-msg` sinh message từ staged diff — **phải `git add` trước**. Shortcut: `npm run commit`.

**Push:** pre-push **không** E2E mặc định; CI vẫn chạy Playwright.

> Commit message: **tiếng Anh** (Conventional). UI app: **tiếng Việt**.

---

## Đặt tên nhánh

```
feat/mo-ta-ngan
fix/ten-loi
chore/cong-viec
refactor/module
docs/cap-nhat-doc
```

Chữ thường, gạch ngang.

---

## Đồng bộ tài liệu (bắt buộc khi đổi hành vi)

Rule: [docs-sync](../../.cursor/rules/docs-sync.mdc)

| Đổi trong code | Cập nhật tối thiểu |
|----------------|-------------------|
| Route | `docs/API_STRUCTURE.md` + doc module |
| Migration / cột | `docs/DATABASE_STRUCTURE.md` |
| Page / component | `docs/FRONTEND_STRUCTURE.md` + doc module |
| Luồng UX | `docs/FLOWS_AND_DOCS_MAP.md` hoặc doc module |
| npm script / CI | `_dev/commands.md`, `_dev/ci-cd.md` + `_dev/vi/` |

Commit chỉ doc: `docs(scope): mo ta`.

Hub: [docs/FLOWS_AND_DOCS_MAP.md](../../docs/FLOWS_AND_DOCS_MAP.md).

---

## Vue / Inertia

JavaScript (không TypeScript).

| Quy tắc | Quy ước |
|---------|---------|
| File | PascalCase, một SFC / component |
| API | `<script setup>` + Composition API |
| Import | `@/` → `resources/js/` |
| Pages | Mỏng trong `Pages/{Domain}/`, `AppLayout` + `#header` `PageHeader` |
| Logic | `composables/use*.js` — không Excel trong `.vue` |
| Feature lớn | `modules/project/`, `modules/aiAccount/` |
| Shared UI | `shared/ui/`, `Components/Ui/` |
| Icon / toast | `AppIcon`, `useToast`, `useDialog` — không `alert()` |

**UI bắt buộc (rule Cursor):**

- Content header: `.cursor/rules/content-header.mdc`
- Toolbar bảng: `.cursor/rules/datagrid-toolbar.mdc`
- KPI strip (khi clone /feedback): `.cursor/rules/kpi-summary-strip.mdc`

Brand `#9A0036` (`brand`), copy user-facing **tiếng Việt**.

---

## PHP / Laravel

| Quy tắc | Quy ước |
|---------|---------|
| DailyReport | Use Case + Domain |
| Project/Task mutations | Application Use Cases |
| Blocker, KB, … | MVC Controller → Model |
| Quyền | `$this->authorize(...)` + Policy |
| Validate | FormRequest, `messages()` tiếng Việt |
| Enum | `app/Support/Enums/` |
| Bảng | prefix `va_prd_` |
| Style | Pint — **CI:** `vendor/bin/pint --test` |
| Phân tích | PHPStan — CI advisory |

Media URL: `PublicMediaUrl` — không expose path thô khi file mất.

---

## ESLint

`eslint.config.js` — ESLint 9 flat; phạm vi `resources/js/**/*.{js,vue}`.

| Rule | Ghi chú |
|------|---------|
| `vue/multi-word-component-names` | off |
| `no-unused-vars` | warn; arg `_` bỏ qua |

`npm run lint` — `--max-warnings=0`. Pre-commit: lint-staged.

---

## Nhập · xuất · đối soát Excel

Spec đầy đủ + sơ đồ: [docs/IMPORT_EXPORT_RECONCILE.md](../../docs/IMPORT_EXPORT_RECONCILE.md).

- Một nút **Dữ liệu** → một modal **3 tab:** nhập · xuất · đối soát
- Logic trong `use*Data.js` / `use*Import.js` — **cấm** `xlsx` trong `.vue`
- Mẫu: Risk/Blocker (`RiskImportModal`, `useRiskImport.js`)
- KB: chỉ xuất JSON + `useKbExport` (không modal 3 tab)

Rule agent: `.cursor/rules/import-export-reconcile.mdc`.

---

## Pre-push / CI (tóm tắt)

| Check | Local thường | CI |
|-------|--------------|-----|
| Pint | Trước push PHP | Blocking |
| ESLint | pre-commit + `npm run lint` | Blocking |
| PHPUnit | `php artisan test` | Blocking |
| E2E | Tùy chọn / trước PR | Blocking |
| PHPStan | Tùy chọn | Advisory |

Skill: [ship-ready](../../.cursor/skills/ship-ready/SKILL.md).
