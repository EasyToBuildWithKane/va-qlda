<?php

namespace App\Support;

/**
 * Temporary in-code list of projects a member can tag a daily report with.
 *
 * The Projects module isn't built yet, so this acts as the single source of
 * truth for the "Dự án trong ngày" picker. When a real `projects` table lands,
 * swap {@see self::options()} for a query and keep the same {id, name, color}
 * shape so the front-end picker keeps working unchanged.
 */
class ProjectCatalog
{
    /**
     * @return array<int, array{id:int, name:string, code:string, color:string}>
     */
    public static function options(): array
    {
        return [
            ['id' => 1, 'name' => 'Cổng thông tin VAschools', 'code' => 'PORTAL', 'color' => 'brand'],
            ['id' => 2, 'name' => 'Ứng dụng Quản lý dự án', 'code' => 'QLDA', 'color' => 'sky'],
            ['id' => 3, 'name' => 'Hệ thống Tuyển sinh', 'code' => 'ADMISSION', 'color' => 'emerald'],
            ['id' => 4, 'name' => 'App Phụ huynh', 'code' => 'PARENT', 'color' => 'violet'],
            ['id' => 5, 'name' => 'Hệ thống LMS', 'code' => 'LMS', 'color' => 'amber'],
            ['id' => 6, 'name' => 'Hạ tầng & DevOps', 'code' => 'INFRA', 'color' => 'rose'],
            ['id' => 7, 'name' => 'Nghiên cứu & R&D', 'code' => 'R&D', 'color' => 'cyan'],
            ['id' => 8, 'name' => 'Hỗ trợ & Bảo trì', 'code' => 'SUPPORT', 'color' => 'slate'],
        ];
    }
}
