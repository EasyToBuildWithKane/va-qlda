<?php

return [

    /*
    |--------------------------------------------------------------------------
    | App identity (admin-editable via /settings → group "general")
    |--------------------------------------------------------------------------
    |
    | Defaults below are the baseline; the system_settings table overrides
    | them at runtime (see App\Providers\SettingsServiceProvider) and they are
    | shared to Inertia as `app.*` for the sidebar brand + page titles.
    |
    */
    'app_name' => env('APP_DISPLAY_NAME', 'VAschools QLDA'),
    'app_short_name' => env('APP_SHORT_NAME', 'VA'),
    'support_email' => env('SUPPORT_EMAIL', 'phongcongnghe@vaschools.edu.vn'),
    /** Email nhận đề xuất PM từ cổng /congnghe/de-xuat */
    'congnghe_proposal_email' => env('CONGNGHE_PROPOSAL_EMAIL', 'phongcongnghe@vaschools.edu.vn'),
    'app_version' => env('APP_DISPLAY_VERSION', '1.0'),

    /*
    |--------------------------------------------------------------------------
    | Password login (dev / E2E only)
    |--------------------------------------------------------------------------
    |
    | Production UI is Google-only. When true, POST /login remains for PHPUnit
    | and Playwright (not exposed on the login page).
    |
    */
    'password_login_enabled' => env('AUTH_PASSWORD_LOGIN', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Google OAuth — allowed email domains (empty = any verified email)
    |--------------------------------------------------------------------------
    */
    'google_allowed_domains' => array_filter(array_map(
        'trim',
        explode(',', (string) env('GOOGLE_ALLOWED_DOMAINS', 'vaschools.edu.vn'))
    )),

    /*
    |--------------------------------------------------------------------------
    | Google OAuth — allowed individual emails (not whole domains)
    |--------------------------------------------------------------------------
    |
    | Comma-separated. Lowercase recommended. Does not open all @gmail.com —
    | only listed addresses may sign in via Google and are limited to Coaching.
    |
    */
    'google_allowed_emails' => array_values(array_unique(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('GOOGLE_ALLOWED_EMAILS', ''))
    )))),

    /*
    |--------------------------------------------------------------------------
    | /tech/login — whitelist (only these emails may use the tech QLDA portal)
    |--------------------------------------------------------------------------
    |
    | Override with TECH_LOGIN_ALLOWED_EMAILS (comma-separated). Empty env uses
    | the default list below.
    |
    */
    'tech_login_allowed_emails' => array_values(array_unique(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env(
            'TECH_LOGIN_ALLOWED_EMAILS',
            'phongcongnghe@vaschools.edu.vn,toanbq@vaschools.edu.vn,hungnv@vaschools.edu.vn,truongnv@vaschools.edu.vn,thangkc@vaschools.edu.vn,locd@hcm.vaschools.edu.vn,khoana@hcm.vaschools.edu.vn,thaipq@hcm.vaschools.edu.vn,kieunlt@hcm.vaschools.edu.vn,hoadt@hcm.vaschools.edu.vn,binhtl@hcm.vaschools.edu.vn,quangtm@hcm.vaschools.edu.vn,truchtm@vaschools.edu.vn,hoangbh@vaschools.edu.vn,vunh@vaschools.edu.vn'
        ))
    )))),

    /*
    |--------------------------------------------------------------------------
    | Landing /congnghe — gốc sơ đồ tổ chức (chỉ nhánh Phòng Công nghệ)
    |--------------------------------------------------------------------------
    |
    | Chuỗi con (không dấu, lowercase) khớp tên nhóm cấp 1. Có thể ghi đè bằng
    | CONGNGHE_ORG_ROOT_PATTERNS (phân tách bằng dấu phẩy).
    |
    */
    'congnghe_org_root_patterns' => array_values(array_filter(array_map(
        static fn (string $part): string => strtolower(trim($part)),
        explode(',', (string) env(
            'CONGNGHE_ORG_ROOT_PATTERNS',
            'phong cong nghe,phong cntt,ban cong nghe,cong nghe thong tin,phong công nghệ,cntt'
        ))
    ))),

    /*
    | Ghi đè: ID nhóm OrgTeam làm gốc sơ đồ /congnghe (CONGNGHE_ORG_ROOT_TEAM_ID).
    */
    'congnghe_org_root_team_id' => ($id = env('CONGNGHE_ORG_ROOT_TEAM_ID')) !== null && $id !== ''
        ? (int) $id
        : null,

    /*
    |--------------------------------------------------------------------------
    | /dashboard — phạm vi nhân sự (báo cáo ngày, KPI thành viên)
    |--------------------------------------------------------------------------
    |
    | Khớp một phần tên phòng ban active (LIKE %pattern%). Ghi đè:
    | DASHBOARD_PERSONNEL_DEPT_PATTERN
    |
    */
    'dashboard_personnel_department_pattern' => env('DASHBOARD_PERSONNEL_DEPT_PATTERN', 'Công nghệ'),

];
