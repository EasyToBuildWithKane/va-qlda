# Credential Management — VA-Workspace

Module quản lý tài khoản, mật khẩu và liên kết hạ tầng nội bộ.

## Route

| URI | Mô tả |
|-----|--------|
| `/credentials` | Danh sách + KPI |
| `/credentials/create` | Tạo mới |
| `/credentials/{id}/edit` | Chỉnh sửa (Inertia) |
| `/credentials/{id}` | Chi tiết 4 tab (Tổng quan · Bảo mật · Phân quyền · Nhật ký) |
| `POST /credentials/import` | Nhập bulk ≤200 |
| `/api/credentials/{id}/password` | Reveal password (audit) |
| `/api/credentials/{id}/access-grants` | ACL |
| `/api/credentials/{id}/access-requests` | Yêu cầu / duyệt quyền |

## Loại → danh mục (`system_category`)

Nguồn chuẩn FE: `resources/js/modules/credential/config/categoriesByType.js` · enum: `App\Support\Enums\CredentialCategory`.

| `credential_type` | Danh mục |
|-------------------|----------|
| `internal_system` | cms, landing_page, crm, erp, hrm, lms, knowledge_base, ai_platform |
| `infrastructure` | vps, server, hosting, cdn, dns, domain, database, mail_server, ssl, **aws** |
| `provider` | cloud_provider, hosting_provider, sms_provider, email_provider, payment_gateway, ai_services, third_party_api, api_key |
| `working_account` | admin_account, user_account, other |

**Thêm cloud sau (Azure, GCP…):** thêm case trong `CredentialCategory` + đẩy value vào `CATEGORIES_BY_TYPE.infrastructure` (Create/Edit/import dùng chung map này). Form vẫn dùng field hiện có (URL, username, mật khẩu, `provider_name`…).

## Backend

- Controllers: `app/Http/Controllers/Credential/*`
- Policy: `CredentialPolicy` — owner/creator, grant; `manageAccess` / tab Phân quyền: admin, người tạo, phụ trách; tab hiện với admin, người tạo/phụ trách hoặc được cấp quyền
- Logger: `CredentialActivityLogger`
- KPI: `CredentialSummaryBuilder`

## Frontend

- Pages: `resources/js/Pages/Credential/`
- Module: `resources/js/modules/credential/`
- Chi tiết `/credentials/{id}`: tab Nhật ký phân trang query `audit_page`, `audit_per_page`; tab Phân quyền datagrid toolbar + API `access-grants.index` đồng bộ danh sách

## Phân quyền nav

- **admin, lead, member:** danh sách (`/credentials`, scope `visibleTo`)
