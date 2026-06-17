<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Tổng quan
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('contract_categories')->nullOnDelete();
            // Bộ hợp đồng: hợp đồng gốc = null; bản gia hạn / phụ lục trỏ về gốc.
            $table->foreignId('root_contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('using_unit')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();

            // Tài chính
            $table->string('currency', 8)->default('VND');
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->decimal('monthly_cost', 15, 2)->nullable();
            $table->decimal('annual_cost', 15, 2)->nullable();
            $table->decimal('lifecycle_cost', 15, 2)->nullable();
            $table->string('payment_status')->default('unpaid');

            // Thời hạn
            $table->string('billing_cycle', 16)->nullable();
            $table->date('signed_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->unsignedSmallInteger('renewal_term_months')->nullable();
            $table->unsignedSmallInteger('notice_period_days')->nullable();

            // Trạng thái
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('health_score')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('expiry_date');
            $table->index('payment_status');
            $table->index(['vendor_id', 'status']);
            $table->index('category_id');
            $table->index('root_contract_id');
        });

        Schema::create('contract_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('category')->default('contract');
            $table->foreignId('uploaded_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('original_name');
            $table->text('notes')->nullable();
            $table->string('path')->nullable();
            $table->string('external_url')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_image')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['contract_id', 'category']);
        });

        Schema::create('contract_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('event', 40);
            $table->text('description');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'created_at'], 'contract_act_contract_created_idx');
        });

        Schema::create('contract_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->date('previous_expiry')->nullable();
            $table->date('new_expiry')->nullable();
            $table->decimal('previous_cost', 15, 2)->nullable();
            $table->decimal('new_cost', 15, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('renewed_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_renewals');
        Schema::dropIfExists('contract_activities');
        Schema::dropIfExists('contract_attachments');
        Schema::dropIfExists('contracts');
    }
};
