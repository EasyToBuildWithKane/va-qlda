<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_purchase_proposals', function (Blueprint $table) {
            $table->string('subject_about', 500)->nullable()->after('tool_name');
            $table->string('send_to', 500)->nullable()->after('subject_about');
            $table->string('proposer_name', 255)->nullable()->after('send_to');
            $table->string('proposer_position', 255)->nullable()->after('proposer_name');
            $table->string('proposer_department', 255)->nullable()->after('proposer_position');
            $table->text('proposal_content')->nullable()->after('justification');
            $table->text('objectives')->nullable()->after('proposal_content');
            $table->unsignedSmallInteger('quantity')->default(1)->after('seats');
            $table->unsignedSmallInteger('staff_count')->nullable()->after('quantity');
            $table->string('recipient_name', 255)->nullable()->after('staff_count');
            $table->string('recipient_position', 255)->nullable()->after('recipient_name');
            $table->string('recipient_email', 255)->nullable()->after('recipient_position');
            $table->string('recipient_phone', 32)->nullable()->after('recipient_email');
            $table->string('purchase_type', 16)->default('new')->after('recipient_phone');
            $table->string('registration_email', 255)->nullable()->after('purchase_type');
            $table->date('planned_use_date')->nullable()->after('registration_email');
        });
    }

    public function down(): void
    {
        Schema::table('ai_purchase_proposals', function (Blueprint $table) {
            $table->dropColumn([
                'subject_about',
                'send_to',
                'proposer_name',
                'proposer_position',
                'proposer_department',
                'proposal_content',
                'objectives',
                'quantity',
                'staff_count',
                'recipient_name',
                'recipient_position',
                'recipient_email',
                'recipient_phone',
                'purchase_type',
                'registration_email',
                'planned_use_date',
            ]);
        });
    }
};
