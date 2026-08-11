# Quản lý AI — Tài khoản & Chi phí

Module: routes `ai-accounts.*`, API `api.ai-accounts.*`, frontend `resources/js/Pages/AiAccount/`, `resources/js/modules/aiAccount/`.

Schema: `docs/DATABASE_STRUCTURE.md` §6.

---

## Phạm vi (2026-08)

Một thực thể **Tài khoản AI** (`AiAccount`) — không còn workflow duyệt PĐX/ĐNTT, OCR quét phiếu, Dashboard BI / Analytics.

| Trang | Route |
|-------|--------|
| Tài khoản AI | `ai-accounts.index` → `/ai-accounts` |
| Chi phí AI | `ai-accounts.cost-report` → `/ai-accounts/cost-report` |

**Visibility:** admin tier thấy tất cả; `created_by` luôn thấy; user khác chỉ thấy tài khoản có grant còn hiệu lực (ít nhất `view`) qua bảng `ai_account_access_grants`.

**Index `/ai-accounts`:** toolbar — ô tìm + **Lọc** + **Thêm tài khoản**; `AppLayout` flush + `p-3 sm:p-4`.

---

## Trường chính trên tài khoản

| Trường | Ý nghĩa |
|--------|---------|
| `created_by` | Người tạo (system_accounts) |
| `tool_name`, `group_function` | Tên công cụ + nhóm (DEV/BA/…) |
| `email_registered` | Email đăng ký |
| `login_method` | `password` (tài khoản thường) \| `google` |
| `login_password` | Encrypted — chỉ khi `login_method=password` |
| `purchase_url` | Link chỗ mua / quản lý license |
| `purchase_date`, `expiry_date` | Ngày mua / hết hạn |
| `notify_before_days` | Nhắc trước N ngày hết hạn |
| `cost_amount`, `cost_unit` | Chi phí + đơn vị (tháng/quý/năm/một lần) |
| `proposal_sent_at`, `proposal_approved_at`, `payment_request_sent_at` | Ngày gửi PĐX / duyệt PĐX / gửi ĐNTT |
| `proposal_document_paths`, `payment_request_document_paths` | Mỗi loại **1 file** gắn 1–1 với ngày gửi |
| `status` | `active` / `expiring_soon` / `expired` / `cancelled` |

Form (`AiAccountFormModal`, `max-w-4xl`): 4 tab **Thông tin** · **Chi phí & hạn** · **Chứng từ** · **Phân quyền**.

- Chi phí: `MoneyInput` → format `1.000.000 ₫`
- Ngày: `FilterDatePicker` (`dd/MM/yyyy`)
- Đăng nhập: segmented Google / Tài khoản thường
- Phân quyền: `AiAccountAccessGrantsPanel` (sau khi đã lưu)

### Quyền ACL (`AiAccountPermission`)

`view` · `view_password` · `edit` · `delete` · `share`

Ability global: `ai_account.share` / `ai_account.manage_access` / `ai_account.view_password` (admin/lead).

---

## API JSON (`api/ai-accounts`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/` | `index` (scoped `visibleTo`) |
| GET | `/summary` | `summary` |
| POST | `/` | `store` |
| GET | `/{aiAccount}` | `show` |
| PUT | `/{aiAccount}` | `update` |
| POST | `/{aiAccount}` | `update.multipart` |
| PATCH | `/{aiAccount}/status` | `update-status` |
| DELETE | `/{aiAccount}` | `destroy` |
| POST | `/{aiAccount}/renew` | `renew` |
| POST | `/trigger-reminder` | `trigger-reminder` |
| GET | `/{aiAccount}/documents/{kind}/{index}` | `documents.file` |
| GET | `/{aiAccount}/access-grants` | `access-grants.index` |
| POST | `/{aiAccount}/access-grants` | `access-grants.store` |
| DELETE | `/{aiAccount}/access-grants/{accessGrant}` | `access-grants.destroy` |

---

## Nhắc hết hạn

`AiAccountReminderService` + schedule `ai-accounts:send-reminders` (08:00, 14:00). Config: `config/ai_accounts.php`.

---

## File tham chiếu

| Thành phần | Path |
|------------|------|
| API / pages | `AiAccountController`, `AiAccountPageController`, `AiAccountAccessController` |
| Policy / grants | `AiAccountPolicy`, `AiAccountAccessGrant` |
| Enums | `AiAccountLoginMethod`, `AiAccountPermission` |
| FE | `AiAccountFormModal`, `AiAccountDocSlot`, `AiAccountAccessGrantsPanel` |
| Test | `tests/Feature/AiAccountTest.php`, `AiAccountAccessTest.php` |
