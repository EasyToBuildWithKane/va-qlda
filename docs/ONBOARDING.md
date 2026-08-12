# Onboarding & Smart Context

API tiến độ tour + cờ ngữ cảnh (shared Inertia prop `onboarding`) vẫn giữ.
**Thẻ gợi ý nổi góc phải (`SmartContextHint`) đã gỡ khỏi `AppLayout`** (2026-07-30).
**Màn hình chào mừng toàn màn hình (Welcome Screen) được thêm** (2026-08-12) —
xem mục riêng bên dưới.

## Welcome Screen (màn hình chào mừng toàn màn hình, lần đăng nhập đầu)

Khái niệm **tách biệt hoàn toàn** khỏi hệ thống TOURS (spotlight từng bước) ở
trên — đây là 1 màn hình chào mừng hiện đúng 1 lần, không phải tour nhiều bước.

| Lớp | Tệp | Vai trò |
|-----|-----|---------|
| Composable | `modules/onboarding/composables/useOnboardingWelcome.js` | Đọc `onboarding.welcome` (shared prop) + gọi API "đã xem" |
| | `modules/onboarding/composables/motion.js` | `prefersReducedMotionNow()` — bản sao thuần của helper cùng tên trong `Pages/Congnghe/partials/motion.js` |
| UI | `modules/onboarding/components/WelcomeScreen.vue` | Overlay full-screen (Teleport to body), mount trong `AppLayout.vue` |
| Backend | `OnboardingService::welcomePayload/markWelcomeSeen/resetWelcomeForAll` | Payload + ghi "đã xem" + reset hàng loạt |
| | `OnboardingController::seenWelcome` | `POST /onboarding/welcome/seen` (204, fire-and-forget) |
| Cấu hình | `SettingsSchema` group `onboarding` (field `welcome_enabled`) | Bật/tắt toàn hệ thống tại `/settings/onboarding` (super-admin) |
| | `SystemSettingController::resetOnboardingWelcome` | `POST /settings/onboarding/reset` — xoá `onboarding_seen_at` hàng loạt |

**Dữ liệu:** cột `system_accounts.onboarding_seen_at` (nullable timestamp,
migration `2026_08_12_130000_add_onboarding_seen_at_to_system_accounts_table`
— cột này từng tồn tại rồi bị drop ở `2026_07_28_180000`, nay thêm lại qua
migration mới thay vì sửa migration cũ đã chạy production).

**Nội dung màn hình:** tên nhân viên, phòng ban (badge màu theo
`Department.color`, dùng `Badge.vue`), vai trò, tối đa 9 đồng nghiệp cùng
phòng ban (avatar qua `Avatar.vue`, tự fallback initials), mascot
`vas-mascot-wave.png` (float bằng class Tailwind global `animate-cn-float`).

**Hiệu năng:** `welcomePayload()` **early-return** (không query
department/coworkers) khi tính năng tắt hoặc tài khoản đã xem — payload này
nằm trong shared Inertia prop nên chạy trên mọi request có auth, phải tránh
N+1 cho user đã xem rồi.

**Super-admin:** `/settings/onboarding` — toggle bật/tắt (`FieldsTab` tái sử
dụng) + nút "Cho mọi người xem lại" (dialog xác nhận qua `useDialog`, ghi
audit qua `SecurityAuditLogger::onboarding()`).

## Kiến trúc

| Lớp | Tệp | Vai trò |
|-----|-----|---------|
| Composable | `composables/useOnboarding.js` | Đọc shared Inertia prop `onboarding` + ghi tiến độ tour (API) qua axios |
| | `composables/useSmartContext.js` | Cờ ngữ cảnh → 1 gợi ý ưu tiên (không mount UI) |
| UI | `components/OnboardingRoot.vue` + `SmartContextHint` | **Không mount** — giữ file nếu bật lại sau |
| Backend | `OnboardingController` + `OnboardingService` + `OnboardingTourRequest` | Tiến độ tour (whitelist key); ngữ cảnh cache |
| | `App\Models\OnboardingProgress` | 1 dòng / (account, tour) |

## Dữ liệu & API

- Bảng `va_prd_onboarding_progress` (status: `in_progress | completed | skipped`).
- Shared Inertia prop `onboarding` (trong `HandleInertiaRequests`): tiến độ + cờ
  ngữ cảnh; mọi page đọc được.
- Routes (`/onboarding`, auth): `index` (JSON), `progress`, `complete`, `skip`,
  `reset`.
- Mutations gọi bằng **axios** → server trả **204** để không re-render Inertia;
  sau hành động kết thúc client có thể `router.reload({ only: ['onboarding'] })`.

## Quyết định production

- **Hiệu năng:** cờ ngữ cảnh (có dự án/sprint/task/thành viên chưa) là dữ liệu
  toàn cục → cache app-wide (`onboarding.context.v1`, TTL 300s) nên prop dùng chung
  thêm ~0 query. Cache tự xoá khi tạo mới Project/Sprint/Task/Employee
  (`Model::created` trong `AppServiceProvider`) — xem `OnboardingService::forgetContext()`.
- **UX:** thẻ nudge góc phải đã tắt; API/context giữ cho mở rộng sau.

## Kiểm thử

- Backend: `php artisan test --filter OnboardingTest` (progress / complete /
  skip / reset / validate tour_key / fallback redirect).
