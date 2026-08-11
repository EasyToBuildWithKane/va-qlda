# Quản lý AI — Tài khoản & Chi phí

Module: routes `ai-accounts.*`, API `api.ai-accounts.*`, frontend `resources/js/Pages/AiAccount/`, `resources/js/modules/aiAccount/`.

Schema: `docs/DATABASE_STRUCTURE.md` §6.

---

## Phạm vi (2026-08)

Một thực thể **Tài khoản AI** (`AiAccount`) — không còn workflow duyệt PĐX/ĐNTT, OCR quét phiếu, password viewers, Dashboard BI / Analytics.

| Trang | Route |
|-------|--------|
| Tài khoản AI | `ai-accounts.index` → `/ai-accounts` |
| Chi phí AI | `ai-accounts.cost-report` → `/ai-accounts/cost-report` |

Nav AI Workspace chỉ còn 2 mục trên.

**Index `/ai-accounts`:** toolbar gọn — ô tìm + **Lọc** + **Thêm tài khoản** (`ml-auto`, không trên `PageHeader`; không nút Cột / Chi phí AI / Nhắc nhở / Thu nhóm; Chi phí AI vào qua nav). Nội dung dùng `AppLayout` flush + `p-3 sm:p-4`.

---

## Trường chính trên tài khoản

| Trường | Ý nghĩa |
|--------|---------|
| `tool_name`, `group_function` | Tên công cụ + nhóm (DEV/BA/…) |
| `email_registered`, `login_password` | Email đăng ký; mật khẩu (encrypted, quyền `ai_account.view_password`) |
| `purchase_date`, `expiry_date` | Ngày mua / hết hạn |
| `notify_before_days` | Nhắc trước N ngày hết hạn |
| `cost_amount`, `cost_unit` | Chi phí + đơn vị (tháng/quý/năm/một lần) |
| `proposal_sent_at`, `payment_request_sent_at` | Ngày gửi đề xuất / đề nghị thanh toán |
| `proposal_document_paths`, `payment_request_document_paths` | File phiếu (JSON: path, original_name, mime, size) |
| `status` | `active` / `expiring_soon` / `expired` / `cancelled` — sync từ `expiry_date` (+ `notify_before_days`) |

Form tạo/sửa (`AiAccountFormModal`): modal `fit-viewport`, 3 tab **Thông tin** · **Chi phí & hạn** · **Chứng từ**; nhãn bắt buộc có dấu `*`; input có placeholder gợi ý.

Chi phí KPI / Chi phí AI: **trực tiếp** từ `cost_amount` (quy tháng qua `AiAccountCostCalculator`) — không còn «phiếu đếm ngân sách».

---

## API JSON (`api/ai-accounts`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/` | `index` |
| GET | `/summary` | `summary` |
| POST | `/` | `store` (JSON hoặc multipart + file) |
| GET | `/{aiAccount}` | `show` |
| PUT | `/{aiAccount}` | `update` |
| POST | `/{aiAccount}` | `update.multipart` (`_method=PUT` + file) |
| PATCH | `/{aiAccount}/status` | `update-status` |
| DELETE | `/{aiAccount}` | `destroy` |
| POST | `/{aiAccount}/renew` | `renew` |
| POST | `/trigger-reminder` | `trigger-reminder` |
| GET | `/{aiAccount}/documents/{kind}/{index}` | `documents.file` (`kind`: `proposal` \| `payment-request`) |

Upload: disk `public`, path `ai-accounts/{id}/proposal|payment-request/…`. URL file chỉ khi tồn tại trên disk.

---

## Nhắc hết hạn

`AiAccountReminderService` + schedule `ai-accounts:send-reminders` (08:00, 14:00). Config: `config/ai_accounts.php` → `reminder.*`.

---

## File tham chiếu

| Thành phần | Path |
|------------|------|
| API / pages | `AiAccountController`, `AiAccountPageController` |
| Nhóm + summary | `AiAccountGrouper`, `AiAccountCostSummaryBuilder`, `AiAccountCostCalculator` |
| File phiếu | `AiAccountDocumentService` |
| Model | `app/Models/AiAccount.php` |
| Test | `tests/Feature/AiAccountTest.php`, `AiAccountStatusUpdateTest.php` |

**Đã gỡ:** PĐX/ĐNTT controllers & tables, OCR (`ocr-service/`, proposal-scans), password viewers, Analytics/Dashboard pages.
