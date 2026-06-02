# Conventions — VA-QLDA

Coding standards enforced by tooling and project rules.

---

## Commit format (enforced by commitlint)

Config: `commitlint.config.js` — extends `@commitlint/config-conventional`.

```
type(scope): description
```

| Rule | Value |
|------|-------|
| **Types** | `feat` · `fix` · `docs` · `style` · `refactor` · `perf` · `test` · `build` · `ci` · `chore` · `revert` |
| **Header max length** | 72 characters |
| **Body line max length** | 100 characters |
| **Subject case** | No sentence-case, start-case, pascal-case, or ALL CAPS |

**Examples:**

```
feat(auth): add Google OAuth login
fix(api): handle null response from payment gateway
chore(deps): upgrade Vue to 3.5.x
refactor(project): extract useSprintData composable
test(e2e): add login failure scenario
```

**Validate manually:**

```bash
echo "feat: test message" | npm run commitlint
```

**Enforcement:** Husky `commit-msg` hook runs commitlint on every commit.

---

## Branch naming

```
feat/short-description
fix/issue-or-description
chore/task-description
refactor/area-name
docs/update-readme
```

Use lowercase, hyphen-separated descriptions. Match commit type prefix when possible.

---

## Vue component conventions

This project uses **JavaScript** (not TypeScript) for frontend code.

| Rule | Convention |
|------|------------|
| **One component per file** | Single `.vue` SFC per component |
| **Filenames** | PascalCase — e.g. `ProjectCard.vue`, `PageHeader.vue` |
| **API style** | Composition API with `<script setup>` |
| **Props** | `defineProps({ ... })` with runtime type validation |
| **Imports** | `@/` alias → `resources/js/` (configured in `vite.config.js`) |
| **Pages** | Thin Inertia pages in `Pages/{Domain}/`, wrap `AppLayout` |
| **Logic** | Extract to `composables/use*.js` — keep `.vue` files focused on UI |
| **Icons** | `<AppIcon name="task" />` — Lucide map in `AppIcon.vue` |
| **Dialogs** | `useDialog`, `useToast` — no native `alert()` |

**Folder layout:**

```
resources/js/
├── Pages/{Domain}/       # Inertia pages
├── Components/Ui/        # Primitives (Modal, Drawer, PageHeader)
├── Components/Project/   # Feature components
├── composables/          # Shared logic
└── Layouts/AppLayout.vue
```

**Copy & UX:** UI text, flash messages, validation errors — **tiếng Việt**.  
Brand color: `#9A0036` (Tailwind `brand` token).

---

## PHP / Laravel conventions

| Rule | Convention |
|------|------------|
| **Architecture** | MVC for Project/Task/Blocker; Clean Architecture for DailyReport |
| **Authorization** | `$this->authorize('manage', $project)` in controllers |
| **Validation** | FormRequest classes with Vietnamese `messages()` |
| **Enums** | `app/Support/Enums/` — backed string enums with `values()`, `label()` |
| **DB tables** | Prefix `va_prd_` |
| **Code style** | Laravel Pint (`composer format`) |
| **Static analysis** | PHPStan via Larastan (`composer analyse`) |

---

## ESLint rules in effect

Config: `eslint.config.js` (ESLint 9 flat config).

**Extends:**

- `@eslint/js` recommended
- `eslint-plugin-vue` flat/recommended

**Scope:** `resources/js/**/*.{js,vue}`

**Ignores:** `public/build/**`, `node_modules/**`, `vendor/**`

**Custom rules:**

| Rule | Setting | Effect |
|------|---------|--------|
| `vue/multi-word-component-names` | `off` | Single-word component names allowed (e.g. `Modal.vue`) |
| `no-unused-vars` | `warn`, `argsIgnorePattern: '^_'` | Unused vars warn; prefix `_` to ignore args |

**CLI:** `npm run lint` fails on any warning (`--max-warnings=0`).

**Pre-commit:** lint-staged runs `eslint --fix --max-warnings=0` on staged files.

---

## Import / export modules

Excel import/export modules must follow the production pattern documented in `.cursor/rules/import-export-reconcile.mdc`:

- One `*DataModal.vue` with 3 tabs: import · export · reconcile
- Logic in `use*Data.js` composables — never import `xlsx` in `.vue`
- Reference implementation: Risk/Blocker (`useRiskImport.js`, `RiskImportModal.vue`)
