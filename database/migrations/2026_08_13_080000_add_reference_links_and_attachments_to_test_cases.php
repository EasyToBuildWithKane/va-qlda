<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('test_cases', 'reference_links')) {
                $table->json('reference_links')->nullable()->after('expected_result');
            }
        });

        if (! Schema::hasTable('test_case_attachments')) {
            Schema::create('test_case_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('test_case_id')->constrained('test_cases')->cascadeOnDelete();
                $table->foreignId('uploaded_by_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->string('original_name');
                $table->string('path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->boolean('is_image')->default(false);
                $table->timestamps();

                $table->index('test_case_id', 'tca_case_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('test_case_attachments');

        Schema::table('test_cases', function (Blueprint $table) {
            if (Schema::hasColumn('test_cases', 'reference_links')) {
                $table->dropColumn('reference_links');
            }
        });
    }
};
