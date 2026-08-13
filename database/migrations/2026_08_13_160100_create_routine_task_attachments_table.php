<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('routine_task_id')->constrained('routine_tasks')->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_image')->default(false);
            $table->timestamps();

            $table->index('routine_task_id', 'rta_task_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_task_attachments');
    }
};
