# Quản lý AI — Tài khoản, PĐX, chi phí theo nhóm

Module: routes `ai-accounts.*`, API `api.ai-accounts.*`, frontend `resources/js/Pages/AiAccount/`, `resources/js/modules/aiAccount/`.

Luồng dữ liệu chi tiết: `docs/DATABASE_STRUCTURE.md` §6 (PĐX, ĐNTT, lifecycle).

---

## Luồng nghiệp vụ (tóm tắt)

1. **PĐX** (`AiPurchaseProposal`) — duyệt → trạng thái `approved` | `purchased` | `active` mới **tính ngân sách** (chi phí / tháng).
2. **ĐNTT** (`AiPaymentRequest`) — phải `approved` hoặc `paid` trước khi **lập tài khoản AI** (`AiAccountFromProposalCreator`).
3. **Tài khoản AI** (`AiAccount`) — soft delete; gắn PĐX qua `ai_purchase_proposals.ai_account_id`.

Chi phí trên **Chi phí theo nhóm** và KPI thẻ tóm tắt lấy từ **phiếu đếm ngân sách**, không lấy trực tiếp từ `cost_amount` trên TK (trừ hiển thị từng dòng TK).

---

## Trạng thái “đếm ngân sách” (countable)

Định nghĩa dùng chung: `AiAccount::countableProposalStatusValues()` / `AiAccountCountableProposalCost::countableStatuses()`:

| PĐX status | Tính chi phí / tháng |
|------------|----------------------|
| `approved`, `purchased`, `active` | Có |
| `pending`, `rejected`, `expired`, … | Không |

PĐX được tính khi: `ai_account_id` null (chờ lập TK) **hoặc** còn TK chưa soft delete (`whereHas('aiAccount')`).

---

## Hiển thị danh sách vs số badge

| Khái niệm | Query / logic |
|-----------|----------------|
| **Danh sách TK** (Index) | `AiAccount::visibleInRegistry()` — TK còn PĐX countable **hoặc** chưa từng gắn PĐX (legacy). |
| **Badge / thẻ `total_accounts`** | Chỉ TK có `accountHasCountableProposal()` (còn PĐX hợp lệ). |
| **Chi phí theo nhóm — cột thống kê TK** | Cùng bộ “registered” như badge; dòng nhóm ẩn khi không TK + chi phí 0 + không phiếu chờ lập TK. |

Subtitle Index: *«Tài khoản đang dùng · liên kết với phiếu đề xuất mua sắm»* — badge phản ánh cột **registered**, không phải mọi bản ghi legacy.

---

## Xóa tài khoản (`DELETE api.ai-accounts.destroy`)

1. Soft delete `AiAccount`.
2. PĐX liên kết (nếu có): `ai_account_id = null`, `status = expired` — **không** còn đếm ngân sách, không quay lại “chờ lập TK” với chi phí cũ.

---

## Xóa PĐX (`DELETE api.ai-accounts.proposals.destroy`)

Hard delete phiếu; nếu có TK gắn `ai_account_id` → **soft delete TK** trước khi xóa phiếu.

---

## TK mồ côi (orphan) — triệu chứng & dọn dẹp

**Triệu chứng:** Badge vẫn **1 TK**, chi phí **0 VNĐ/tháng**, nhóm BA vẫn «1 hoạt động» sau khi user nghĩ đã xóa.

**Nguyên nhân:** TK vẫn tồn tại trong DB nhưng PĐX đã `expired` / `rejected` hoặc đã gỡ `ai_account_id` (dữ liệu cũ hoặc thao tác không đồng bộ).

**Dọn tự động:** Mỗi lần `AiAccountController::loadAndSyncAccounts()` gọi `AiAccount::purgeOrphanedFromProposal()`:

- Soft delete TK **đã từng cấp phát** (`allocated_at` hoặc `purchased_by`) nhưng không còn PĐX countable.
- Soft delete TK còn liên kết PĐX `expired` | `rejected`.

Sau deploy, user **tải lại** trang Index hoặc Chi phí theo nhóm (một request API là đủ).

**Cache:** `AiAccountCountableProposalCost` **không** giữ cache request — tránh chi phí cũ khi Octane / nhiều lần gọi summary trong cùng process.

---

## File tham chiếu (code)

| Thành phần | Path |
|------------|------|
| API TK | `app/Http/Controllers/AiAccount/AiAccountController.php` |
| API PĐX | `app/Http/Controllers/AiAccount/AiPurchaseProposalController.php` |
| Nhóm + summary cards | `app/Services/AiAccount/AiAccountGrouper.php` |
| Chi phí từ PĐX | `app/Services/AiAccount/AiAccountCountableProposalCost.php` |
| Scope registry / purge | `app/Models/AiAccount.php` |
| KPI workflow | `app/Services/AiAccount/AiWorkflowMetricsBuilder.php` |
| Dashboard & báo cáo BI | `app/Services/AiAccount/AiExecutiveAnalyticsBuilder.php`, `AiAnalyticsController`, pages `Dashboard.vue`, `AnalyticsReport.vue` |
| Test xóa + ngân sách | `tests/Feature/AiAccountSoftDeleteVisibilityTest.php` |
| Test orphan | `tests/Feature/AiAccountOrphanPurgeTest.php` |

---

## Dashboard & báo cáo phân tích (2026-06)

| Trang | Route | API |
|-------|--------|-----|
| Dashboard quản trị | `ai-accounts.dashboard` → `/ai-accounts/dashboard` | `GET api/ai-accounts/analytics/dashboard` |
| Báo cáo chi tiết | `ai-accounts.analytics` → `/ai-accounts/analytics` | `GET api/ai-accounts/analytics/report` |

Chi phí ngân sách / KPI thẻ: PĐX countable + ĐNTT đã thanh toán (giống mục «Luồng nghiệp vụ»). Phòng ban trên biểu đồ: `department_using` / `proposer_department` trên PĐX. «Tỷ lệ sử dụng»: lifecycle `in_use` + trạng thái active. Cảnh báo «không dùng X ngày»: proxy từ `last_reminded_at` / `allocated_at`.

**Dashboard — 6 thẻ KPI (lưới 3×2):** (1) TK đang dùng + tỷ lệ sử dụng; (2) sắp hết hạn / đã hết hạn; (3) chi phí tháng + TB/người; (4) chi phí năm + `cost_forecast_year_end` (nội suy theo ĐNTT YTD); (5) ngân sách duyệt / thanh toán / sử dụng; (6) vận hành PĐX/tháng + `monthly_run_rate_change_percent` (so chi phí tháng vs tháng trước khi có ĐNTT).

---

## Agent / rule

- Sửa bug hiển thị hoặc đếm TK/chi phí: đọc file này trước; **không** thêm cache proposal toàn process; giữ đồng bộ destroy TK ↔ PĐX.
- Pattern UI bảng: `.cursor/rules/datagrid-toolbar.mdc`. Import/xuất (nếu mở rộng): `.cursor/rules/import-export-reconcile.mdc`.
