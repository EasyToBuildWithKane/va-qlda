<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaching_courses', function (Blueprint $table) {
            $table->string('student_name', 255)->nullable()->after('objectives');
            $table->string('coach_name', 255)->nullable()->after('student_name');
        });
    }

    public function down(): void
    {
        Schema::table('coaching_courses', function (Blueprint $table) {
            $table->dropColumn(['student_name', 'coach_name']);
        });
    }
};
