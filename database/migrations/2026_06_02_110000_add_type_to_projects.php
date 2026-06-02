<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phân loại dự án theo vòng đời (nghiên cứu phát triển / triển khai nghiệm thu /
 * vận hành cải tiến) — dùng cho bộ lọc và bảng Kanban danh mục dự án.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('type')->default('rnd')->after('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
