# Onboarding & Smart Context

Gợi ý ngữ cảnh (nudge) giúp người dùng mới biết bước tiếp theo trong hệ thống
(vd. tạo dự án đầu tiên). Không còn FAB «Trợ giúp» / tour spotlight driver.js.

## Kiến trúc

| Lớp | Tệp | Vai trò |
|-----|-----|---------|
| Composable | `composables/useOnboarding.js` | Đọc shared Inertia prop `onboarding` + ghi tiến độ tour (API) qua axios |
| | `composables/useSmartContext.js` | Cờ ngữ cảnh → 1 gợi ý ưu tiên cao nhất |
| UI | `components/OnboardingRoot.vue` | Mount 1 lần trong `AppLayout.vue` |
| | `SmartContextHint` | Thẻ gợi ý góc phải (Teleport body) |
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
- **UX:** chỉ một gợi ý ưu tiên cao nhất; user có thể đóng (dismiss session).

## Kiểm thử

- Backend: `php artisan test --filter OnboardingTest` (progress / complete /
  skip / reset / validate tour_key / fallback redirect).
