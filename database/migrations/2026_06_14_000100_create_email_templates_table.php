<?php

use App\Support\Mail\EmailTemplateDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('body_html');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $names = [
            'task_assigned' => 'Thông báo giao việc',
            'daily_summary' => 'Tổng hợp công việc trong ngày',
            'sprint_summary' => 'Tổng hợp công việc theo sprint',
        ];
        $rows = [];
        foreach ($names as $key => $name) {
            $defaults = EmailTemplateDefaults::forKey($key);
            $rows[] = [
                'key' => $key,
                'name' => $name,
                'subject' => $defaults['subject'],
                'body_html' => $defaults['body_html'],
                'is_active' => true,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('email_templates')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
