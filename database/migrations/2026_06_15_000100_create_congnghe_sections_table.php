<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('congnghe_sections', function (Blueprint $table) {
            $table->id();
            // Section key, khớp config/congnghe.php (vd. "hero", "about").
            $table->string('key')->unique();
            // Override nội dung (JSON). null ⇒ dùng default từ config.
            $table->json('content')->nullable();
            // Hiển thị section trên trang (chỉ áp dụng cho section orderable).
            $table->boolean('is_visible')->default(true);
            // Thứ tự trong luồng <main>.
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('congnghe_sections');
    }
};
