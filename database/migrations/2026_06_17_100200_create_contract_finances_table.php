<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bóc tách chi phí hợp đồng (sheet ContractFinances): mỗi dòng = một khoản
 * sử dụng (số lượng × đơn giá + phí khởi tạo + phí duy trì × thời hạn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_finances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->date('used_date')->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->decimal('init_fee', 15, 2)->nullable();
            $table->decimal('maintenance_fee', 15, 2)->nullable();
            $table->unsignedSmallInteger('term_months')->nullable();
            $table->decimal('total', 15, 2)->nullable();
            $table->decimal('renewal_cost', 15, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_finances');
    }
};
