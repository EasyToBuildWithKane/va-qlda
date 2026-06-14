<?php

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

        DB::table('email_templates')->insert([
            [
                'key' => 'task_assigned',
                'name' => 'Thông báo giao việc',
                'subject' => '[QLDA] Giao việc: {{task_name}} — {{project_name}}',
                'body_html' => '<p>Xin chào <strong>{{assignee_name}}</strong>,</p>'
                    .'<p>Bạn được giao công việc mới trong dự án <strong>{{project_name}}</strong>.</p>'
                    .'<p><strong>{{task_name}}</strong></p>'
                    .'<p>Sprint: {{sprint_name}}<br>Hạn: {{due_date}}</p>'
                    .'<p><a href="{{task_url}}">Mở công việc</a></p>',
                'is_active' => true,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'daily_summary',
                'name' => 'Tổng hợp công việc trong ngày',
                'subject' => '[QLDA] Tổng hợp {{date}} — {{project_name}}',
                'body_html' => '<p>Xin chào <strong>{{assignee_name}}</strong>,</p>'
                    .'<p>Danh sách công việc của bạn trong ngày <strong>{{date}}</strong> (dự án {{project_name}}):</p>'
                    .'{{tasks_table}}'
                    .'<p>Tổng: {{task_count}} công việc.</p>',
                'is_active' => true,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'sprint_summary',
                'name' => 'Tổng hợp công việc theo sprint',
                'subject' => '[QLDA] Sprint {{sprint_name}} — {{project_name}}',
                'body_html' => '<p>Xin chào <strong>{{assignee_name}}</strong>,</p>'
                    .'<p>Công việc được giao trong sprint <strong>{{sprint_name}}</strong> (dự án {{project_name}}):</p>'
                    .'{{tasks_table}}'
                    .'<p>Tổng: {{task_count}} công việc.</p>',
                'is_active' => true,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
