<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SystemRole 5->7: 'lead' bị thay bằng manager/deputy_manager/team_leader.
 * Mọi tài khoản đang có role='lead' được chuyển thành 'team_leader' — không
 * có dữ liệu cũ nào cho manager/deputy_manager nên không cần phán đoán theo
 * từng người (admin gán lại thủ công qua /settings nếu cần nâng cấp).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_accounts')->where('role', 'lead')->update(['role' => 'team_leader']);
    }

    public function down(): void
    {
        DB::table('system_accounts')->where('role', 'team_leader')->update(['role' => 'lead']);
    }
};
