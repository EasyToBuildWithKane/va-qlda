# FOLDER STRUCTURE — VA-Workspace

> **Cập nhật 2026-06-19.** Hub luồng: [`FLOWS_AND_DOCS_MAP.md`](FLOWS_AND_DOCS_MAP.md) · Frontend chi tiết: [`FRONTEND_STRUCTURE.md`](FRONTEND_STRUCTURE.md).

Refactor Phase 1–5 ✅. Layout đã ổn định: backend phân lớp `Application/` + `Domain/` cho module clean, MVC cho module còn lại; frontend gom theo `modules/{feature}/`.

---

## 1. Cây thư mục

```
va-workspace/
├── _dev/                     ← Operational memory (CLI, CI, workflows) + _dev/vi/
├── docs/                     ← Technical documentation (file này)
├── app/
│   ├── Application/          ← Use Cases: DailyReport/, Project/, Task/
│   ├── Domain/DailyReport/   ← Domain models (UUID) + ScoringService
│   ├── Http/
│   │   ├── Controllers/      ← Gom theo domain: Project/, Contract/, Credential/, …
│   │   ├── Requests/         ← FormRequest (authorize + rules + messages VN)
│   │   ├── Resources/        ← Inertia props (+ `can`)
│   │   └── Middleware/       ← HandleInertiaRequests, …
│   ├── Models/               ← Eloquent (App\Models\*) — prefix bảng va_prd_
│   ├── Policies/             ← $account->allows('module.action') OR ownership
│   ├── Services/             ← NotificationService, NotificationDispatcher, …
│   ├── Support/              ← Enums/, Options/, Auth/PermissionCatalog, Navigation, *ActivityLogger
│   ├── Observers/ Events/ Listeners/ Mail/ Console/ Providers/
├── config/                   ← business.php (hằng số), + overlay settings runtime
├── routes/
│   ├── web.php               ← Loader mỏng: wire 2 nhóm middleware → require web/*.php
│   ├── web/                  ← 15 partial theo domain (xem §3)
│   ├── api.php               ← Rỗng (không REST API chính)
│   ├── channels.php console.php
├── resources/js/             ← Frontend (xem FRONTEND_STRUCTURE.md §3)
├── database/                 ← migrations, seeders, factories
├── tests/                    ← Feature/ (PHPUnit) + e2e/ (Playwright)
├── scripts/  .husky/  .github/workflows/ci.yml
└── .claude/  .cursor/        ← AI rules, skills, slash commands
```

---

## 2. Backend Application Layer

```
app/Application/
├── DailyReport/   ← Full Clean Architecture (Use Case + Domain)
├── Project/       ← Create, Update, Duplicate, Archive, LogWork
└── Task/          ← Create, Patch, Update, BulkCreate, Import (+ Concerns/)
```

**Quy ước pattern:**
- Project/Task mutations → Use Case; read paths vẫn MVC.
- DailyReport → Use Case + Domain model (`App\Domain\DailyReport\Models\`).
- Blocker, Feedback, Contract, Credential, KnowledgeBase, … → MVC: Controller → Model/Support.

> **Không** refactor sang Use Case khi chỉ sửa bug nhỏ. Module mới: ưu tiên FormRequest + Policy + Resource giống module cùng loại.

---

## 3. Routes — split theo domain

`routes/web.php` chỉ wire 2 ngữ cảnh middleware rồi `require` các partial:

- **Guest** (`middleware('guest')`): `web/auth.php` — login portals + OAuth.
- **Auth** (`middleware('auth')`): 14 partial còn lại.

| Partial | Phạm vi |
|---|---|
| `auth.php` | Đăng nhập, SSO HRM, Google OAuth, hidden admin login |
| `dashboard.php` | `/dashboard` (HubDashboardController), `/work`, logout |
| `congnghe.php` | Landing Phòng Công Nghệ + đề xuất phần mềm |
| `platform.php` | performance, onboarding, notifications, audit |
| `daily-reports.php` | Báo cáo ngày |
| `projects.php` | Dự án, sprint, epic, task, worklog, member, attachment |
| `blockers.php` | Vướng mắc + attachment |
| `contracts.php` | Quản lý hợp đồng (CLM) |
| `feedback.php` | Phản hồi |
| `knowledge-base.php` | Tri thức |
| `ai-accounts.php` | Tài khoản AI (pages + JSON) |
| `credentials.php` | Kho mật khẩu (pages + JSON) |
| `people.php` | Profile + department mutate API (org Index/UI gỡ — HRM) |
| `settings.php` | Cấu hình hệ thống (super-admin) |
| `workspace-config.php` | Hub cấu hình workspace + đánh giá (super-admin) |
| `comments.php` | Bình luận đa hình + realtime token |

> Mỗi partial tự `use` controller riêng và đăng ký route trong group đang active. Static segment đặt **trước** `/{id}`.

---

## 4. Frontend (tóm tắt)

```
resources/js/
├── Pages/{Domain}/      ← Inertia pages (mỏng, bọc AppLayout)
├── Layouts/AppLayout.vue
├── Components/Ui/, Layout/, Notifications/   ← primitives + app shell
├── modules/{feature}/   ← 12 module: project, daily-report, knowledge-base,
│                          contract, credential, performance,
│                          profile, onboarding, notifications, aiAccount, audit
├── shared/ui/, shared/composables/, shared/services/
├── composables/         ← Feature logic (useSprint*, useRisk*, …)
├── stores/              ← Pinia: auth.js, ui.js
└── constants/index.js   ← mirror config/business.php
```

Mỗi `modules/{feature}/` chứa `components/` (+ `composables/`, `config/` khi cần). Catalog đầy đủ: [`FRONTEND_STRUCTURE.md`](FRONTEND_STRUCTURE.md).

**Import alias:** `@/modules/...`, `@/shared/...`, `@/Components/...`, `@/composables/...`, `@/stores/...`

---

## 5. Còn lại / chưa làm

| Item | Ghi chú |
|---|---|
| Domain layer cho Project/Task | Mới có Application Use Cases, chưa có `app/Domain/Project/` |
| `services/http.js` toàn diện | API calls vẫn phần lớn inline Inertia/axios (`shared/services/http.js` cho JSON) |
| TypeScript / JSDoc types | Chưa có |
| Lowercase `pages/` | Bỏ qua — rủi ro Windows case-insensitive |

Chi tiết nợ kỹ thuật: [`TECHNICAL_DEBT.md`](TECHNICAL_DEBT.md). Lịch sử refactor: [`REFACTOR_PLAN.md`](REFACTOR_PLAN.md).

---

## 6. Tài liệu liên quan

| File | Nội dung |
|---|---|
| [`FRONTEND_STRUCTURE.md`](FRONTEND_STRUCTURE.md) | Component catalog, composables, UI patterns |
| [`ARCHITECTURE.md`](ARCHITECTURE.md) | Trách nhiệm từng lớp, transport |
| [`API_STRUCTURE.md`](API_STRUCTURE.md) | Route map đầy đủ |
| [`_dev/README.md`](../_dev/README.md) | CLI, CI, workflows (operational) |
