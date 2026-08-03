<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loại đánh giá hệ thống: code + description (PERIODICAL / CONTRACT / TYPE_PROBATION).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('evaluation_form_types')) {
            return;
        }

        Schema::table('evaluation_form_types', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_form_types', 'code')) {
                $table->string('code', 64)->nullable()->after('name');
            }
            if (! Schema::hasColumn('evaluation_form_types', 'description')) {
                $table->text('description')->nullable()->after('code');
            }
        });

        try {
            Schema::table('evaluation_form_types', function (Blueprint $table) {
                $table->unique('code', 'eval_form_type_code_uq');
            });
        } catch (\Throwable) {
            // Index may already exist on re-run.
        }

        $now = now();
        $types = [
            [
                'code' => 'PERIODICAL',
                'name' => 'Đánh giá định kỳ',
                'description' => 'Loại đánh giá theo kỳ (thường là quý, nửa năm, một năm) mục đích để review quá trình làm việc của nhân sự từ đó để xuất tăng lương, bổ nhiệm.',
                'sort_order' => 0,
            ],
            [
                'code' => 'CONTRACT',
                'name' => 'Đánh giá chuyển hợp đồng',
                'description' => 'Loại đánh giá sau khi kết thúc 1 hợp đồng lao động, mục đích giúp ban lãnh đạo có quyết định ký mới hợp đồng nhân sự này không.',
                'sort_order' => 1,
            ],
            [
                'code' => 'TYPE_PROBATION',
                'name' => 'Đánh giá thử việc',
                'description' => 'Loại đánh giá sau khi kết thúc quá trình thử việc (về cơ bản tương tự đánh giá chuyển hợp đồng) tuy nhiên hệ thống tách thành 1 loại đánh giá để phục vụ cho việc thống kê.',
                'sort_order' => 2,
            ],
        ];

        foreach ($types as $type) {
            $existingByCode = DB::table('evaluation_form_types')
                ->where('code', $type['code'])
                ->first();

            if ($existingByCode) {
                DB::table('evaluation_form_types')
                    ->where('id', $existingByCode->id)
                    ->update([
                        'name' => $type['name'],
                        'description' => $type['description'],
                        'sort_order' => $type['sort_order'],
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);

                continue;
            }

            $existingByName = DB::table('evaluation_form_types')
                ->where('name', $type['name'])
                ->first();

            if ($existingByName) {
                DB::table('evaluation_form_types')
                    ->where('id', $existingByName->id)
                    ->update([
                        'code' => $type['code'],
                        'description' => $type['description'],
                        'sort_order' => $type['sort_order'],
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('evaluation_form_types')->insert([
                'name' => $type['name'],
                'code' => $type['code'],
                'description' => $type['description'],
                'sort_order' => $type['sort_order'],
                'is_active' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('evaluation_form_types')) {
            return;
        }

        Schema::table('evaluation_form_types', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_form_types', 'code')) {
                try {
                    $table->dropUnique('eval_form_type_code_uq');
                } catch (\Throwable) {
                    // ignore
                }
                $table->dropColumn('code');
            }
            if (Schema::hasColumn('evaluation_form_types', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
