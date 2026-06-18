# Onboarding & Interactive Tour

Hướng dẫn tương tác (spotlight) giúp người dùng mới làm quen hệ thống: màn hình
chào mừng khi đăng nhập lần đầu, tour theo vai trò, gợi ý theo ngữ cảnh và nút
trợ giúp nổi để xem lại bất cứ lúc nào.

## Kiến trúc

| Lớp | Tệp | Vai trò |
|-----|-----|---------|
| Thư viện | `driver.js` | Spotlight overlay + popover + step nav (bọc trong composable) |
| Nội dung tour | `resources/js/modules/onboarding/tours/index.js` | Registry khai báo các bước (single source) |
| Composable | `composables/useTour.js` | Bọc driver.js — 1 tour chạy tại một thời điểm, dọn dẹp, reduced-motion |
| | `composables/useOnboarding.js` | State từ shared prop + ghi tiến độ qua axios (fire-and-forget) |
| | `composables/useSmartContext.js` | Cờ ngữ cảnh → 1 gợi ý ưu tiên cao nhất |
| UI | `components/OnboardingRoot.vue` | Điều phối; mount 1 lần trong `AppLayout.vue` |
| | `WelcomeModal` · `TourProgressHud` · `TourCompleteModal` · `SmartContextHint` · `HelpWidget` | |
| Backend | `OnboardingController` + `OnboardingService` + `OnboardingTourRequest` | Lưu tiến độ; nội dung tour ở client |
| | `App\Models\OnboardingProgress` | 1 dòng / (account, tour) |

## Dữ liệu & API

- Bảng `va_prd_onboarding_progress` (status: `in_progress | completed | skipped`) +
  cột `system_accounts.onboarding_seen_at` (điều khiển màn chào lần đầu).
- Shared Inertia prop `onboarding` (trong `HandleInertiaRequests`): tiến độ + cờ
  ngữ cảnh; mọi page đọc được.
- Routes (`/onboarding`, auth): `index` (JSON), `progress`, `complete`, `skip`,
  `reset`, `dismiss-welcome`.
- Mutations gọi bằng **axios** → server trả **204** để không re-render Inertia khi
  đang chạy tour; sau hành động kết thúc client tự `router.reload({ only: ['onboarding'] })`.

## Quyết định production

- **Hiệu năng:** cờ ngữ cảnh (có dự án/sprint/task/thành viên chưa) là dữ liệu
  toàn cục → cache app-wide (`onboarding.context.v1`, TTL 300s) nên prop dùng chung
  thêm ~0 query. Cache tự xoá khi tạo mới Project/Sprint/Task/Employee
  (`Model::created` trong `AppServiceProvider`) — xem `OnboardingService::forgetContext()`.
- **Robust:** chỉ 1 tour chạy cùng lúc; dọn overlay khi unmount; chặn double-start;
  bước trỏ tới phần tử không có trong DOM (nav ẩn theo role) sẽ tự bỏ qua.
- **A11y/UX:** khoá scroll nền khi mở modal, Esc để đóng, tôn trọng
  `prefers-reduced-motion`, đóng menu trợ giúp khi click ra ngoài / điều hướng.
  `HelpWidget`: mục **Ẩn nút trợ giúp** (lưu `localStorage` `va_qlda_help_widget_hidden`);
  khi ẩn hiện chip **Trợ giúp** góc phải để bật lại FAB.

## Thêm / sửa tour

1. Thêm key vào `OnboardingService::TOURS` (server whitelist) **và** registry
   `tours/index.js` (giữ đồng bộ).
2. Mỗi bước anchor tới `data-tour="..."`. Anchor dùng chung nằm ở sidebar group
   (`nav-{key}`), topbar (`topbar-notifications`, `topbar-user`) và `help-widget`.
   Page riêng: thêm `data-tour` vào phần tử của page đó.

## Kiểm thử

- Backend: `php artisan test --filter OnboardingTest` (10 ca: progress / complete /
  skip / reset / dismiss / validate tour_key / fallback redirect).
