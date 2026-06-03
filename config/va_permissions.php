<?php

/**
 * Role → permission matrix and production bootstrap admin emails.
 *
 * Run after CMS sync: php artisan db:seed --class=BootstrapAdminSeeder
 * Or: php artisan va:bootstrap-admins
 */
return [

    'permissions' => [
        'system.access_admin_nav' => 'Menu quản trị (thông báo, cấu hình)',
        'notifications.manage' => 'Quản lý thông báo hệ thống',
        'departments.manage' => 'Quản lý phòng ban',
        'projects.manage' => 'Tạo/sửa/xóa dự án',
        'projects.contribute' => 'Thao tác công việc trong dự án',
        'daily_reports.review' => 'Duyệt báo cáo ngày',
        'daily_reports.submit' => 'Nộp báo cáo ngày',
        'users.manage_roles' => 'Gán role tài khoản (tương lai)',
    ],

    /*
    | Wildcard * = full access for that role.
    */
    'role_grants' => [
        'admin' => ['*'],
        'lead' => [
            'departments.manage',
            'projects.manage',
            'projects.contribute',
            'daily_reports.review',
            'daily_reports.submit',
        ],
        'member' => [
            'projects.contribute',
            'daily_reports.submit',
        ],
        'viewer' => [
            'projects.contribute',
        ],
    ],

    /*
    | Emails (lowercase) → system_accounts.role after CMS sync / provision.
    | Tất cả dưới đây: admin (quyền cao nhất trong QLDA hiện tại).
    */
    'bootstrap_accounts' => [
        'kieunlt@hcm.vaschools.edu.vn' => 'admin',
        'khoana@hcm.vaschools.edu.vn' => 'admin',
        'hoadtt@hcm.vaschools.edu.vn' => 'admin',
        'quangtm@hcm.vaschools.edu.vn' => 'admin',
        'binhtl@hcm.vaschools.edu.vn' => 'admin',
        'truchtm@vaschools.edu.vn' => 'admin',
        'toanbq@vaschools.edu.vn' => 'admin',
        'hungnv@vaschools.edu.vn' => 'admin',
        'hoangbh@vaschools.edu.vn' => 'admin',
    ],

    /*
    | Email trong CMS/Google khác domain so với bootstrap_accounts.
    */
    'bootstrap_email_aliases' => [
        'truchtm@vaschools.edu.vn' => ['truchtm@hcm.vaschools.edu.vn'],
        'toanbq@vaschools.edu.vn' => ['toanbq@hcm.vaschools.edu.vn'],
        'hungnv@vaschools.edu.vn' => ['hungnv@hcm.vaschools.edu.vn'],
        'hoangbh@vaschools.edu.vn' => ['hoangbh@hcm.vaschools.edu.vn'],
    ],

];
