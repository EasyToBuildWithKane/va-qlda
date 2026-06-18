<?php

use App\Support\Mail\EmailTemplateDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $names = [
            'congnghe_proposal_submitted' => 'Đề xuất PM — gửi Phòng Công nghệ',
            'congnghe_proposal_rejected' => 'Đề xuất PM — thông báo từ chối',
        ];

        $now = now();

        foreach ($names as $key => $name) {
            if (DB::table('email_templates')->where('key', $key)->exists()) {
                continue;
            }

            $defaults = EmailTemplateDefaults::forKey($key);
            DB::table('email_templates')->insert([
                'key' => $key,
                'name' => $name,
                'subject' => $defaults['subject'],
                'body_html' => $defaults['body_html'],
                'is_active' => true,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->whereIn('key', ['congnghe_proposal_submitted', 'congnghe_proposal_rejected'])
            ->delete();
    }
};
