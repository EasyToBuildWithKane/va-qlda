<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team members assigned to a project, each carrying the billing rate used for
 * labour-cost roll-ups (rate can differ per project, hence stored on the pivot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('developer'); // pm/lead/developer/qa/designer
            $table->string('rate_type')->default('hourly'); // hourly|monthly
            $table->decimal('rate', 15, 2)->nullable();
            $table->unsignedTinyInteger('allocation')->nullable(); // % of capacity
            $table->date('joined_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_member');
    }
};
