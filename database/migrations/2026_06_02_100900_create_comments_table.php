<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic discussion thread shared by bugs, feedback, blockers and tasks
 * (e.g. người dùng phản hồi ↔ người sửa trao đổi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable'); // commentable_type + commentable_id
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('author_name')->nullable(); // external author
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
