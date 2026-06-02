<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simulated auth accounts (custom guard). Not the Laravel default users table.
 * `employee_id` links a login to a person (nullable until mapped).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');                  // bcrypt
            $table->string('display_name');
            $table->string('role')->default('member');   // SystemRole enum value
            $table->foreignId('employee_id')->nullable()
                ->constrained('employees')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['is_active', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_accounts');
    }
};
