<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_attachments')) {
            Schema::create('project_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id');
                $table->string('category', 32);
                $table->unsignedBigInteger('uploaded_by_id')->nullable();
                $table->unsignedBigInteger('updated_by_id')->nullable();
                $table->string('original_name');
                $table->string('path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->boolean('is_image')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('project_id', 'proj_att_project_fk')
                    ->references('id')->on('projects')->cascadeOnDelete();
                $table->foreign('uploaded_by_id', 'proj_att_uploader_fk')
                    ->references('id')->on('employees')->nullOnDelete();
                $table->foreign('updated_by_id', 'proj_att_updated_by_fk')
                    ->references('id')->on('employees')->nullOnDelete();

                $table->index(['project_id', 'category'], 'proj_att_project_cat_idx');
            });
        }

        if (! Schema::hasTable('project_attachment_activities')) {
            Schema::create('project_attachment_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_attachment_id');
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('event', 40);
                $table->text('description');
                $table->json('meta')->nullable();
                $table->timestamps();

                // MySQL identifier limit 64 chars — default FK names are too long.
                $table->foreign('project_attachment_id', 'proj_att_act_att_fk')
                    ->references('id')->on('project_attachments')->cascadeOnDelete();
                $table->foreign('employee_id', 'proj_att_act_emp_fk')
                    ->references('id')->on('employees')->nullOnDelete();

                $table->index(['project_attachment_id', 'created_at'], 'proj_att_act_att_id_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_attachment_activities');
        Schema::dropIfExists('project_attachments');
    }
};
