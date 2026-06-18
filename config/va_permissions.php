<?php

use App\Support\Auth\PermissionCatalog;

/**
 * Role → permission matrix and production bootstrap admin emails.
 *
 * The permission catalog (module → abilities) and the default role grants live
 * in {@see App\Support\Auth\PermissionCatalog}; the matrix is editable at
 * /settings (permissions tab) and overlaid onto `role_grants` at boot.
 *
 * Run after CMS sync: php artisan db:seed --class=BootstrapAdminSeeder
 * Or: php artisan va:bootstrap-admins
 */
return [

    /*
    | Role → permission keys. Wildcards: '*' = full access, '{module}.*' /
    | '{module}.manage' = every ability in a module. Defaults mirror the
    | historical policy behaviour (see PermissionCatalog::defaultGrants).
    */
    'role_grants' => PermissionCatalog::defaultGrants(),

    /*
    | Emails (lowercase) → system_accounts.role after CMS sync / provision.
    | super_admin: độc quyền cấu hình hệ thống + ma trận phân quyền + gán role.
    | admin: full nghiệp vụ nhưng không truy cập cấu hình/phân quyền.
    */
    'bootstrap_accounts' => [
        'kieunlt@hcm.vaschools.edu.vn' => 'super_admin',
        'khoana@hcm.vaschools.edu.vn' => 'super_admin',
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
