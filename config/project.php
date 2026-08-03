<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phòng ban phụ trách mặc định (dự án nội bộ Phòng Công nghệ)
    |--------------------------------------------------------------------------
    |
    | Mã phòng ban trong bảng departments (ưu tiên mã HRM, vd. PCN).
    | Có thể ghi đè bằng PROJECT_DEFAULT_DEPARTMENT_CODE.
    |
    */
    'default_owner_department_code' => env('PROJECT_DEFAULT_DEPARTMENT_CODE', 'PCN'),

];
