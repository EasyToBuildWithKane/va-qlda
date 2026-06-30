<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('project_attachments', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('category');
                $table->foreign('parent_id', 'proj_att_parent_fk')
                    ->references('id')->on('project_attachments')->cascadeOnDelete();
                $table->index(['project_id', 'category', 'parent_id'], 'proj_att_proj_cat_parent_idx');
            }
            if (! Schema::hasColumn('project_attachments', 'is_folder')) {
                $table->boolean('is_folder')->default(false)->after('parent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('project_attachments', 'parent_id')) {
                $table->dropForeign('proj_att_parent_fk');
                $table->dropIndex('proj_att_proj_cat_parent_idx');
                $table->dropColumn('parent_id');
            }
            if (Schema::hasColumn('project_attachments', 'is_folder')) {
                $table->dropColumn('is_folder');
            }
        });
    }
};
