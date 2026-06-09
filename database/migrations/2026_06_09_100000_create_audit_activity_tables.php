<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('event', 40);
            $table->text('description');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at'], 'proj_act_proj_created_idx');
        });

        Schema::create('bug_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bug_id')->constrained('bugs')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('event', 40);
            $table->text('description');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['bug_id', 'created_at'], 'bug_act_bug_created_idx');
        });

        Schema::create('feedback_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedbacks')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('event', 40);
            $table->text('description');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['feedback_id', 'created_at'], 'fb_act_fb_created_idx');
        });

        Schema::create('security_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_account_id')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at'], 'sec_audit_action_created_idx');
            $table->index(['subject_type', 'subject_id'], 'sec_audit_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_logs');
        Schema::dropIfExists('feedback_activities');
        Schema::dropIfExists('bug_activities');
        Schema::dropIfExists('project_activities');
    }
};
