<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('credential_type', 32);
            $table->string('system_category', 32);
            $table->string('login_url', 2048)->nullable();
            $table->string('username', 255)->nullable();
            $table->text('login_password')->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('provider_name', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->string('environment', 16)->default('production');
            $table->string('status', 16)->default('active');
            $table->boolean('mfa_enabled')->default(false);
            $table->string('recovery_email', 255)->nullable();
            $table->string('recovery_phone', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('password_expires_at')->nullable();
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_critical')->default(false);
            $table->json('badges')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'credential_type'], 'cred_status_type_idx');
            $table->index(['system_category', 'expires_at'], 'cred_cat_exp_idx');
            $table->index('owner_id', 'cred_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
