<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core "people" table. Every domain entity (reports, projects, blockers, …)
 * references an employee. Login lives in system_accounts, linked by FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();           // e.g. EMP-001
            $table->string('full_name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('role_title')->nullable();    // job title, free text
            $table->date('join_date')->nullable();
            $table->json('skills')->nullable();          // ["php","vue",...]
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();            // extensible fields
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
