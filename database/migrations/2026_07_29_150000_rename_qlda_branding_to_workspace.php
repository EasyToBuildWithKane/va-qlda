<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product rename QLDA → Workspace: project code, KB tag slug, settings defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects')) {
            DB::table('projects')->where('code', 'QLDA')->update(['code' => 'WORKSPACE']);
            DB::table('projects')->where('code', 'Workspace')->update(['code' => 'WORKSPACE']);
        }

        if (Schema::hasTable('kb_tags')) {
            $hasNew = DB::table('kb_tags')->where('slug', 'va-workspace')->exists();
            $old = DB::table('kb_tags')->where('slug', 'va-qlda')->first();
            if ($old && ! $hasNew) {
                DB::table('kb_tags')->where('id', $old->id)->update([
                    'slug' => 'va-workspace',
                    'name' => 'VA-Workspace',
                ]);
            } elseif ($old && $hasNew) {
                // Prefer new slug; re-point pivot then drop duplicate old tag.
                if (Schema::hasTable('kb_article_tags')) {
                    $newId = DB::table('kb_tags')->where('slug', 'va-workspace')->value('id');
                    $oldIds = DB::table('kb_article_tags')->where('tag_id', $old->id)->pluck('article_id');
                    foreach ($oldIds as $articleId) {
                        $exists = DB::table('kb_article_tags')
                            ->where('article_id', $articleId)
                            ->where('tag_id', $newId)
                            ->exists();
                        if ($exists) {
                            DB::table('kb_article_tags')
                                ->where('article_id', $articleId)
                                ->where('tag_id', $old->id)
                                ->delete();
                        } else {
                            DB::table('kb_article_tags')
                                ->where('article_id', $articleId)
                                ->where('tag_id', $old->id)
                                ->update(['tag_id' => $newId]);
                        }
                    }
                }
                DB::table('kb_tags')->where('id', $old->id)->delete();
            }
        }

        if (Schema::hasTable('system_settings')) {
            $replacements = [
                'VAschools QLDA' => 'VAschools Workspace',
                'VA-QLDA' => 'VA-Workspace',
                'VA QLDA' => 'VA Workspace',
                '[QLDA]' => '[Workspace]',
            ];
            $rows = DB::table('system_settings')->get(['id', 'value']);
            foreach ($rows as $row) {
                $value = (string) $row->value;
                $next = $value;
                foreach ($replacements as $from => $to) {
                    $next = str_replace($from, $to, $next);
                }
                if ($next !== $value) {
                    DB::table('system_settings')->where('id', $row->id)->update(['value' => $next]);
                }
            }
        }

        if (Schema::hasTable('email_templates')) {
            $replacements = [
                '[QLDA]' => '[Workspace]',
                'VAschools QLDA' => 'VAschools Workspace',
                'VA-QLDA' => 'VA-Workspace',
            ];
            $rows = DB::table('email_templates')->get(['id', 'subject', 'body_html']);
            foreach ($rows as $row) {
                $subject = (string) $row->subject;
                $body = (string) $row->body_html;
                $nextSubject = $subject;
                $nextBody = $body;
                foreach ($replacements as $from => $to) {
                    $nextSubject = str_replace($from, $to, $nextSubject);
                    $nextBody = str_replace($from, $to, $nextBody);
                }
                if ($nextSubject !== $subject || $nextBody !== $body) {
                    DB::table('email_templates')->where('id', $row->id)->update([
                        'subject' => $nextSubject,
                        'body_html' => $nextBody,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects')) {
            DB::table('projects')->where('code', 'WORKSPACE')->update(['code' => 'QLDA']);
        }

        if (Schema::hasTable('kb_tags')) {
            DB::table('kb_tags')->where('slug', 'va-workspace')->update([
                'slug' => 'va-qlda',
                'name' => 'VA-QLDA',
            ]);
        }
    }
};
