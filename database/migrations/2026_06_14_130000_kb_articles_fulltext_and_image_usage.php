<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_article_images', function (Blueprint $table) {
            $table->string('usage', 20)->default('inline')->after('alt_text');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE kb_articles ADD FULLTEXT kb_articles_fulltext (title, excerpt, content)');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE kb_articles DROP INDEX kb_articles_fulltext');
        }

        Schema::table('kb_article_images', function (Blueprint $table) {
            $table->dropColumn('usage');
        });
    }
};
