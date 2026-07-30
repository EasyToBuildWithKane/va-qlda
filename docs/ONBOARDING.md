# Onboarding & Smart Context

API tiến độ tour + cờ ngữ cảnh (shared Inertia prop `onboarding`) vẫn giữ.
**Thẻ gợi ý nổi góc phải (`SmartContextHint`) đã gỡ khỏi `AppLayout`** (2026-07-30).

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
