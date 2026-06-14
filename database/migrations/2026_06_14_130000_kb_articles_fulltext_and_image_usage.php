<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kb_article_images', 'usage')) {
            Schema::table('kb_article_images', function (Blueprint $table) {
                $table->string('usage', 20)->default('inline')->after('alt_text');
            });
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $articlesTable = Schema::getConnection()->getTablePrefix().'kb_articles';

        $fulltextExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $articlesTable)
            ->where('index_name', 'kb_articles_fulltext')
            ->exists();

        if (! $fulltextExists) {
            DB::statement(
                "ALTER TABLE `{$articlesTable}` ADD FULLTEXT `kb_articles_fulltext` (`title`, `excerpt`, `content`)"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $articlesTable = Schema::getConnection()->getTablePrefix().'kb_articles';

            $fulltextExists = DB::table('information_schema.statistics')
                ->where('table_schema', DB::raw('DATABASE()'))
                ->where('table_name', $articlesTable)
                ->where('index_name', 'kb_articles_fulltext')
                ->exists();

            if ($fulltextExists) {
                DB::statement("ALTER TABLE `{$articlesTable}` DROP INDEX `kb_articles_fulltext`");
            }
        }

        if (Schema::hasColumn('kb_article_images', 'usage')) {
            Schema::table('kb_article_images', function (Blueprint $table) {
                $table->dropColumn('usage');
            });
        }
    }
};
