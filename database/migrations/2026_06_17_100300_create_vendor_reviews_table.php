<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đánh giá định kỳ nhà cung cấp (NCC): 6 tiêu chí điểm (thang 0–10) → tổng
 * điểm → đề xuất gia hạn / đổi NCC. Gắn ở cấp NCC (không gắn hợp đồng).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('reviewed_at')->nullable();

            // Tiêu chí (0–10): chất lượng DV, SLA, tốc độ, giá hài lòng, ổn định, thái độ.
            $table->decimal('service_quality', 4, 2)->nullable();
            $table->decimal('sla', 4, 2)->nullable();
            $table->decimal('speed', 4, 2)->nullable();
            $table->decimal('price_satisfaction', 4, 2)->nullable();
            $table->decimal('stability', 4, 2)->nullable();
            $table->decimal('attitude', 4, 2)->nullable();
            $table->decimal('total_score', 5, 2)->nullable();

            $table->string('recommendation', 24)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'reviewed_at'], 'vendor_review_vendor_date_idx');
            $table->index('recommendation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_reviews');
    }
};
