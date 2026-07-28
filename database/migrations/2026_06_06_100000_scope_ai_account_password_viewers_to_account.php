<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_account_password_viewers')) {
            return;
        }

        if ($this->isAlreadyScoped()) {
            return;
        }

        DB::table('ai_account_password_viewers')->delete();

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->recreateTableSqlite();

            return;
        }

        $this->upgradeTableMysql();
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasColumn('ai_account_password_viewers', 'ai_account_id')) {
            return;
        }

        DB::table('ai_account_password_viewers')->delete();

        $this->dropForeignIfExists('ai_account_password_viewers', 'ai_account_password_viewers_granted_by_foreign');
        $this->dropForeignIfExists('ai_account_password_viewers', 'ai_account_password_viewers_system_account_id_foreign');
        $this->dropIndexIfExists('ai_account_password_viewers', 'ai_pwd_viewer_acct_user_uniq');

        Schema::table('ai_account_password_viewers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_account_id');
            $table->unique('system_account_id');
            $table->foreign('system_account_id')->references('id')->on('system_accounts')->cascadeOnDelete();
            $table->foreign('granted_by')->references('id')->on('system_accounts')->nullOnDelete();
        });
    }

    private function isAlreadyScoped(): bool
    {
        if (! Schema::hasColumn('ai_account_password_viewers', 'ai_account_id')) {
            return false;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return true;
        }

        // Cần cả unique composite + FK — tránh coi “đã xong” khi ALTER fail giữa chừng
        // (errno 121 do tên constraint có prefix va_prd_).
        return $this->hasCompositeUnique()
            && $this->foreignKeyExists('ai_account_password_viewers', 'system_account_id');
    }

    private function hasCompositeUnique(): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return false;
        }

        $connection = Schema::getConnection();
        $table = $connection->getTablePrefix().'ai_account_password_viewers';
        $db = $connection->getDatabaseName();

        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$db, $table, 'ai_pwd_viewer_acct_user_uniq'],
        );

        if ($rows !== []) {
            return true;
        }

        // Legacy auto-named index on prefixed tables
        $rows = DB::select(
            "SELECT index_name FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ?
             AND index_name LIKE '%ai_account_id%system_account_id%'
             AND non_unique = 0
             LIMIT 1",
            [$db, $table],
        );

        return $rows !== [];
    }

    private function recreateTableSqlite(): void
    {
        Schema::drop('ai_account_password_viewers');
        Schema::create('ai_account_password_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('ai_account_id')->constrained('ai_accounts')->cascadeOnDelete();
            $table->foreignId('system_account_id')->constrained('system_accounts')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();
            $table->unique(['ai_account_id', 'system_account_id'], 'ai_pwd_viewer_acct_user_uniq');
        });
    }

    private function upgradeTableMysql(): void
    {
        // Always recreate: ALTER + dropForeign theo tên không prefix fail trên MySQL
        // khi table_prefix = va_prd_ (constraint thực tế là va_prd_…_foreign → errno 121).
        // up() đã wipe rows trước khi gọi — an toàn.
        $this->recreateTableMysql();
    }

    private function recreateTableMysql(): void
    {
        Schema::drop('ai_account_password_viewers');
        Schema::create('ai_account_password_viewers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('ai_account_id')->constrained('ai_accounts')->cascadeOnDelete();
            $table->foreignId('system_account_id')->constrained('system_accounts')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('system_accounts')->nullOnDelete();
            $table->timestamps();
            $table->unique(['ai_account_id', 'system_account_id'], 'ai_pwd_viewer_acct_user_uniq');
        });
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $prefixed = Schema::getConnection()->getTablePrefix().$table;
        $db = Schema::getConnection()->getDatabaseName();

        $rows = DB::select(
            'SELECT 1 FROM information_schema.key_column_usage
             WHERE table_schema = ? AND table_name = ? AND column_name = ?
             AND referenced_table_name IS NOT NULL
             LIMIT 1',
            [$db, $prefixed, $column],
        );

        return $rows !== [];
    }

    private function dropForeignIfExists(string $table, string $foreignName): void
    {
        $connection = Schema::getConnection();
        $prefixed = $connection->getTablePrefix().$table;
        $db = $connection->getDatabaseName();
        $candidates = array_values(array_unique([
            $foreignName,
            $connection->getTablePrefix().$foreignName,
        ]));

        foreach ($candidates as $name) {
            $exists = DB::select(
                'SELECT 1 FROM information_schema.table_constraints
                 WHERE table_schema = ? AND table_name = ? AND constraint_name = ? AND constraint_type = ?
                 LIMIT 1',
                [$db, $prefixed, $name, 'FOREIGN KEY'],
            );

            if ($exists === []) {
                continue;
            }

            DB::statement("ALTER TABLE `{$prefixed}` DROP FOREIGN KEY `{$name}`");

            return;
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $connection = Schema::getConnection();
        $prefixed = $connection->getTablePrefix().$table;
        $db = $connection->getDatabaseName();
        $candidates = array_values(array_unique([
            $indexName,
            $connection->getTablePrefix().$indexName,
        ]));

        foreach ($candidates as $name) {
            $exists = DB::select(
                'SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?
                 LIMIT 1',
                [$db, $prefixed, $name],
            );

            if ($exists === []) {
                continue;
            }

            DB::statement("ALTER TABLE `{$prefixed}` DROP INDEX `{$name}`");

            return;
        }
    }
};
