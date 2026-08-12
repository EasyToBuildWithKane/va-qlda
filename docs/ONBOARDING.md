# Onboarding & Smart Context

API tiến độ tour + cờ ngữ cảnh (shared Inertia prop `onboarding`) vẫn giữ.
**Thẻ gợi ý nổi góc phải (`SmartContextHint`) đã gỡ khỏi `AppLayout`** (2026-07-30).
**Màn hình chào mừng toàn màn hình (Welcome Screen)** — xem mục riêng bên dưới.

## Welcome Screen (màn hình chào mừng toàn màn hình, lần đăng nhập đầu)

Khái niệm **tách biệt hoàn toàn** khỏi hệ thống TOURS (spotlight từng bước) ở
trên — đây là 1 màn hình chào mừng hiện đúng 1 lần, không phải tour nhiều bước.

| Lớp | Tệp | Vai trò |
|-----|-----|---------|
| Composable | `modules/onboarding/composables/useOnboardingWelcome.js` | Đọc `onboarding.welcome` + preview override (shared module state) |
| | `modules/onboarding/composables/motion.js` | `prefersReducedMotionNow()` |
| UI | `modules/onboarding/components/WelcomeScreen.vue` | Overlay full-screen (Teleport), mount **luôn** trong `AppLayout` (không gắn `renderSidebarHere` — AppChrome khiến cờ đó = false) |
| | `modules/onboarding/components/WelcomePanel.vue` | Thẻ nội dung (overlay + preview compact trên settings) |
| Backend | `OnboardingService::welcomePayload($account, force:)` / `markWelcomeSeen` / `resetWelcomeForAll` / `resetWelcomeFor` | Payload + ghi «đã xem» + reset |
| | `OnboardingController::seenWelcome` | `POST /onboarding/welcome/seen` (204) |
| Cấu hình | `config/va.php` → `onboarding_welcome_enabled` + `SettingsSchema` group `onboarding` | Bật/tắt tại `/settings/onboarding` |
| | `SystemSettingController::resetOnboardingWelcome` | `POST /settings/onboarding/reset` — xoá `onboarding_seen_at` hàng loạt |
| | `SystemSettingController::resetOnboardingWelcomeSelf` | `POST /settings/onboarding/reset-self` — chỉ tài khoản hiện tại |
| Settings UI | `Pages/Settings/partials/OnboardingTab.vue` | Hero + preview card + «Xem thử toàn màn hình» + toggle + reset |

**Dữ liệu:** cột `system_accounts.onboarding_seen_at` (nullable timestamp,
migration `2026_08_12_130000_add_onboarding_seen_at_to_system_accounts_table`).

**Nội dung màn hình:** tên nhân viên, phòng ban (ưu tiên pivot
`department_member`, fallback `employees.meta` từ HRM — cùng nguồn hồ sơ),
vai trò, tối đa 9 đồng nghiệp (pivot members hoặc peers cùng `meta.department_*`),
mascot `vas-mascot-wave.png` (`animate-cn-float`).

**Hiệu năng:** `welcomePayload()` **early-return** khi tắt hoặc đã xem (trừ
`force: true` cho preview settings).

**Super-admin `/settings/onboarding`:** preview nhúng + xem thử overlay (không
ghi «đã xem»), «Hiện lại cho tôi», toggle `welcome_enabled`, «Cho mọi người xem lại».

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
  `reset`, `welcome/seen`.
- Mutations gọi bằng **axios** → server trả **204** để không re-render Inertia;
  sau hành động kết thúc client có thể `router.reload({ only: ['onboarding'] })`.

## Quyết định production

- **Hiệu năng:** cờ ngữ cảnh cache app-wide (`onboarding.context.v1`, TTL 300s).
- **UX:** thẻ nudge góc phải đã tắt; Welcome Screen mount qua AppLayout kể cả khi shell là AppChrome.

## Kiểm thử

- Backend: `php artisan test --filter OnboardingTest` (progress / complete /
  skip / reset / welcome seen).
- Settings: `php artisan test --filter=onboarding` trong `SystemSettingTest`
  (preview prop, reset-self, reset-all).
