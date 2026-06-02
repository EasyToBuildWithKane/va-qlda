<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_account_id')->nullable()->constrained('system_accounts')->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('category', 32);
            $table->string('priority', 16)->default('medium');
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreignId('actor_account_id')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->unsignedBigInteger('sprint_id')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->string('entity_type', 64)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action_url', 512)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('assigned_to_account_id')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->boolean('is_admin_feed')->default(false);
            $table->timestamps();

            $table->index(['recipient_account_id', 'read_at', 'created_at'], 'app_notif_recipient_read_idx');
            $table->index(['is_admin_feed', 'created_at'], 'app_notif_admin_feed_idx');
            $table->index(['category', 'created_at'], 'app_notif_category_idx');
            $table->index(['priority', 'created_at'], 'app_notif_priority_idx');
            $table->index(['project_id', 'created_at'], 'app_notif_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
