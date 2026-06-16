# Credential Management — VA-QLDA

Module quản lý tài khoản, mật khẩu và liên kết hạ tầng nội bộ.

## Route

| URI | Mô tả |
|-----|--------|
| `/credentials` | Danh sách + KPI |
| `/credentials/dashboard` | Tổng quan + audit gần đây |
| `/credentials/create` | Tạo mới |
| `/credentials/reports` | Báo cáo bảo mật (admin) |
| `/credentials/{id}` | Chi tiết 5 tab |
| `POST /credentials/import` | Nhập bulk ≤200 |
| `/api/credentials/{id}/password` | Reveal password (audit) |
| `/api/credentials/{id}/access-grants` | ACL |
| `/api/credentials/{id}/access-requests` | Yêu cầu / duyệt quyền |

## Backend

- Controllers: `app/Http/Controllers/Credential/*`
- Policy: `CredentialPolicy` — owner, grant, admin
- Logger: `CredentialActivityLogger`
- KPI: `CredentialSummaryBuilder`

## Frontend

- Pages: `resources/js/Pages/Credential/`
- Module: `resources/js/modules/credential/`

## Phân quyền nav

- **admin, lead:** dashboard + danh sách
- **member:** danh sách (scope `visibleTo`)
- **admin:** báo cáo bảo mật
