<?php

use App\Support\Mail\EmailTemplateDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['task_assigned', 'daily_summary', 'sprint_summary'] as $key) {
            $defaults = EmailTemplateDefaults::forKey($key);
            DB::table('email_templates')
                ->where('key', $key)
                ->whereNull('updated_by')
                ->update([
                    'subject' => $defaults['subject'],
                    'body_html' => $defaults['body_html'],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Irreversible content refresh.
    }
};
