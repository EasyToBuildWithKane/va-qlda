<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_purchase_proposals', function (Blueprint $table) {
            $table->string('proposal_code', 30)->nullable()->unique()->after('id');
            $table->string('proposal_type', 30)->nullable()->after('proposal_code');
            $table->string('vendor_name')->nullable()->after('tool_name');
            $table->string('vendor_website')->nullable()->after('vendor_name');
            $table->text('description')->nullable()->after('proposal_content');
            $table->text('reason_for_proposal')->nullable()->after('description');
            $table->text('expected_benefit')->nullable()->after('reason_for_proposal');
            $table->unsignedBigInteger('actual_cost')->nullable()->after('cost_amount');
            $table->date('start_date')->nullable()->after('planned_use_date');
            $table->date('end_date')->nullable()->after('start_date');
            $table->json('users_list')->nullable()->after('staff_count');
            $table->string('department_using')->nullable()->after('users_list');
            $table->json('attachment_paths')->nullable()->after('department_using');
        });
    }

    public function down(): void
    {
        Schema::table('ai_purchase_proposals', function (Blueprint $table) {
            $table->dropColumn([
                'proposal_code',
                'proposal_type',
                'vendor_name',
                'vendor_website',
                'description',
                'reason_for_proposal',
                'expected_benefit',
                'actual_cost',
                'start_date',
                'end_date',
                'users_list',
                'department_using',
                'attachment_paths',
            ]);
        });
    }
};
