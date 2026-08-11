# Contract Lifecycle Management (CLM) — VA-Workspace

> Module **Quản lý Hợp đồng** — theo dõi hợp đồng nhà cung cấp, tài chính, đánh giá,
> gia hạn (phụ lục) và chi phí. MVC pattern (giống Blocker / AiAccount, **không** Use Case).
> Đường dẫn gốc: **`/contracts`** · Nav group `contracts` (`App\Support\Navigation`).
> **Cập nhật:** 2026-06-18.

---

## 1. Tổng quan

CLM giải bài toán: hợp đồng phần mềm / dịch vụ phân tán, không theo dõi được hạn gia hạn,
chi phí định kỳ và chất lượng nhà cung cấp. Module gom về một nơi:

- **Explorer** dạng cây **NCC → nhóm dịch vụ → hợp đồng** (`/contracts`).
- **Dashboard** KPI + biểu đồ (`/contracts/dashboard`).
- **Chi phí** (`/contracts/cost`) — tổng hợp theo NCC / đơn vị / nhóm / quý + dự báo năm sau.
- **Báo cáo** (`/contracts/reports`) — theo NCC / nhóm / đơn vị / năm, xuất Excel + CSV.
- **Vendors** (`/contracts/vendors`) — hồ sơ NCC + đánh giá 6 tiêu chí.
- **Chi tiết hợp đồng** (`/contracts/{id}`) — tài chính, đánh giá, hồ sơ, gia hạn nhanh. Bản **gia hạn / phụ lục mới** (`root_contract_id` có giá trị): tab **Tổng quan · Đánh giá NCC · Hồ sơ** (không tab Tài chính / Gia hạn).

**Mã tự sinh:** hợp đồng `HD-{yy}-{seq}`, NCC `NCC-{seq}`. Bảng `contracts` có **SoftDeletes**.

---

## 2. Route

Tất cả trong nhóm `contracts.*` (`routes/web/contracts.php`). Static segment đặt **trước** `/{contract}`.

| URI | Tên | Mô tả |
|-----|-----|-------|
| `GET /contracts` | `index` | Explorer (cây NCC→dịch vụ→HĐ) + portfolio summary |
| `GET /contracts/dashboard` | `dashboard` | KPI + biểu đồ (chart.js) |
| `GET /contracts/cost` | `cost` | Tổng hợp chi phí + dự báo |
| `GET /contracts/reports` | `reports` | Báo cáo đa chiều |
| `GET /contracts/export` | `export` | JSON cho client xuất Excel |
| `POST /contracts/import` | `import` | Nhập bulk ≤200 (đa sheet) |
| `GET\|POST\|PUT\|DELETE /contracts/vendors{,/import,/{vendor}}` | `vendors.*` | CRUD NCC + import |
| `POST\|PUT\|DELETE /contracts/vendors/{vendor}/reviews{,/{review}}` | `vendors.reviews.*` | Đánh giá NCC |
| `POST\|PUT\|DELETE /contracts/categories{,/{category}}` | `categories.*` | Nhóm dịch vụ (cho Explorer) |
| `POST /contracts` · `GET\|PUT\|DELETE /contracts/{contract}` | `store/show/update/destroy` | CRUD hợp đồng |
| `POST /contracts/{contract}/renewals` | `renewals.store` | Gia hạn nhanh → tạo HĐ phụ lục |
| `POST\|PUT\|DELETE /contracts/{contract}/finances{,/{finance}}` | `finances.*` | Tài chính (CRUD từ Show) |
| `POST\|DELETE /contracts/{contract}/reviews{,/{review}}` | `reviews.*` | Đánh giá hợp đồng (gắn vendor + contract_id) |
| `GET\|POST\|DELETE /contracts/{contract}/attachments/...` | `attachments.*` | Hồ sơ: upload + link ngoài + version |
| `GET\|POST\|PUT\|DELETE /api/contracts/vendors/...` | `api.contracts.vendors.*` | Endpoint phụ NCC (JSON) |

> **Đã gỡ (refactor 2026-06-17):** Alert Center + trang Renewals + lịch hợp đồng. Quick-renew
> (`renewals.store`) vẫn giữ. Nav hiện chỉ còn: dashboard · explorer · vendors · cost · reports.

---

## 3. Backend

- **Controllers** (`app/Http/Controllers/Contract/`, 11 file): `ContractController` (index/show/store/update/destroy/export/import), `ContractDashboardController`, `ContractCostController`, `ContractReportController`, `ContractFinanceController`, `ContractRenewalController`, `ContractReviewController`, `ContractAttachmentController`, `ContractCategoryController`, `VendorController`, `VendorReviewController`.
- **Models** (`app/Models/`): `Contract` (`root_contract_id` self-FK → phụ lục; `finances()`, `reviews()`/`latestReview()` hasMany), `Vendor`, `ContractCategory`, `ContractFinance`, `ContractRenewal`, `ContractReview`, `VendorReview`, `ContractActivity`, `ContractAttachment`.
- **Engine:** `App\Support\ContractLifecycle\ContractMetricsEngine` — `build()` (dashboard), `buildCost()`, `buildReport()`. `ContractRenewalCalculator` (buckets 90/60/30/7). Activity: `App\Support\ContractActivityLogger`.
- **Reminder cron:** `App\Services\Contract\ContractReminderService` chạy bởi command `contracts:send-reminders` (Kernel daily 08:00) — tự suy `expiring_soon` / `expired` từ `expiry_date` (**không nhập tay**); thông báo `NotificationType::SystemContractExpiry` / `SystemContractExpired`.
- **Inbox:** tạo/sửa HĐ → `SystemContractCreated` / `SystemContractUpdated`; đánh giá NCC → `SystemContractVendorReview` (`NotificationDispatcher` — owner/manager HĐ khi gắn HĐ; đánh giá gốc trên `/contracts/vendors/{id}` chỉ admin feed; `notifyAdmins` bỏ qua account đã nhận bản in-app để tránh trùng).
- **Enums** (`app/Support/Enums/`): `ContractStatus` (draft·active·expiring_soon·expired·pending_renewal·addendum·terminated), `ContractPaymentStatus`, `ContractBillingCycle` (one_time·monthly·quarterly·annual), `ContractAttachmentCategory`, `ContractReviewRecommendation`.
- **Cấu hình ngưỡng:** nav **Cấu hình chung** → **Hợp đồng (CLM)** (`/settings/clm`) → `clm.renewal_alert_days` (mặc định `90,60,30,7`), overlay lên `config('clm.*')` (`config/clm.php` + `SettingsServiceProvider`). Xem `docs/SYSTEM_CONFIG.md`.

---

## 4. Vòng đời & gia hạn

```
draft (Đang chờ duyệt) → active → [expiring_soon → expired]   (cron tự suy theo expiry_date)
                                  └─ renewals.store → tạo Contract mới (status=active,
                                                       root_contract_id = id gốc bộ)
                                     HĐ được gia hạn → status=addendum («Chuyển phụ lục»)
```

- **Gia hạn = tạo bản hợp đồng kế tiếp** (bản cũ chuyển «Chuyển phụ lục», bản mới «Đang hiệu lực»); `contract_renewals` là audit log song song.
- **Tài chính** (`contract_finances`): SL × đơn giá, phí khởi tạo / duy trì, thời hạn, tổng — CRUD ở tab Tài chính của **hợp đồng gốc**; mỗi dòng gắn `contract_id` (gốc hoặc phụ lục trong bộ), bảng gộp `chain_finances`.
- **Đánh giá hợp đồng / NCC** (`vendor_reviews`): 6 tiêu chí **1–10** + tổng + `recommendation`. Đánh giá **gốc** (`contract_id` null) → `security_audit_logs` (`vendor.review_*`); đánh giá gắn HĐ → `contract_activities` (`vendor_review_*`). Tab **Nhật ký** trên `/contracts/{id}` hiển thị `contract_activities` (tạo HĐ, sửa, gia hạn, đánh giá từ tab HĐ…).

---

## 5. Phân quyền

`ContractPolicy` + `VendorPolicy` (đăng ký ở `AuthServiceProvider`):

- **admin / lead:** full quyền (tạo, sửa, xóa, gia hạn, tài chính, đánh giá, import).
- **viewer:** chỉ xem.
- **member:** ẩn (không có nav, không vào được).

Frontend dùng `can` từ Resource + `usePage().props.auth.user.role`.

---

## 6. Nhập · Xuất (theo chuẩn `docs/IMPORT_EXPORT_RECONCILE.md`)

- Một nút **Dữ liệu** → một `ContractDataModal.vue` 3 tab (import / export / reconcile).
- Composable: `useContractImport.js` / `useContractExport.js` (`xlsx-js-style`), `useContractReportExport.js`.
- **Import đa sheet:** parse `Contracts` + `ContractFinances` + `Reviews` của file thật **và** template VA (marker `VA_CLM_IMPORT_V1` trên sheet "Nhap lieu"). Hợp đồng upsert theo `code` (Mã HĐ); NCC/nhóm chưa có = cảnh báo (không chặn), controller `import()` tự tạo (`firstOrCreate`). Payload `{rows, finances, reviews}` ghép server-side theo code trong **một transaction**. Max **200 rows** client + server.

---

## 7. Frontend

- **Pages** (`resources/js/Pages/Contract/`): `Dashboard`, `Index` (Explorer), `Show`, `Vendors`, `VendorShow` (lịch sử đánh giá tách **gốc** / **theo hợp đồng** trong `VendorReviewHistoryPanel`).
- **Module** (`resources/js/modules/contract/`): `components/` (KPI `*SummaryBar` bọc `shared/ui/KpiSummaryStrip`, charts `StatusDonut`/`CostTrendChart`), `composables/` (`useContractExplorer.js` dựng cây client-side, import/export, `useContractFormat.js` — dashboard KPI tiền dùng `formatMoneyCompact` dạng `4,2 triệu vnđ`), `config/`.
- **Dashboard header:** chỉ chọn kỳ (Tháng/Quý/Năm); Explorer vẫn vào qua nav **Danh mục hợp đồng**.
- **Lưu ý unwrap:** trang nhận một `new ContractResource(...)` phải `props.contract?.data ?? props.contract` (Inertia bọc single JsonResource dưới key `data`).

---

## 8. Cơ sở dữ liệu

Bảng `va_prd_*` (migrations `2026_06_17_*`) — chi tiết tại `docs/DATABASE_STRUCTURE.md` mục 10:
`vendors`, `contract_categories`, `contracts`, `contract_finances`, `contract_attachments`,
`contract_renewals`, `contract_activities`, `vendor_reviews`, `contract_reviews`.

---

Liên quan: `docs/DATABASE_STRUCTURE.md` · `docs/API_STRUCTURE.md` · `docs/SYSTEM_CONFIG.md` · `docs/IMPORT_EXPORT_RECONCILE.md`.
