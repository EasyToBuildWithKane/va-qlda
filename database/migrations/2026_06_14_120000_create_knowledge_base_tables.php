<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('color', 50)->nullable();
            $table->string('icon', 50)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('kb_categories')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('kb_categories')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('employees')->cascadeOnDelete();
            $table->string('title', 500);
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index('status');
        });

        Schema::create('kb_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('color', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('kb_article_tags', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('kb_tags')->cascadeOnDelete();
            $table->primary(['article_id', 'tag_id']);
        });

        Schema::create('kb_article_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('employees')->cascadeOnDelete();
            $table->string('original_name', 500);
            $table->string('path', 1000);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('alt_text', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('kb_article_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('employees')->cascadeOnDelete();
            $table->string('original_name', 500);
            $table->string('path', 1000);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('kb_article_favorites', function (Blueprint $table) {
            $table->foreignId('system_account_id')->constrained('system_accounts')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['system_account_id', 'article_id']);
        });

        Schema::create('kb_article_reads', function (Blueprint $table) {
            $table->foreignId('system_account_id')->constrained('system_accounts')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamp('created_at')->nullable();
            $table->primary(['system_account_id', 'article_id']);
        });

        $now = now();
        $categories = [
            ['name' => 'Kiến thức chung', 'slug' => 'general', 'sort_order' => 1],
            ['name' => 'Kiến thức Development', 'slug' => 'development', 'sort_order' => 2],
            ['name' => 'Kiến thức Business Analyst (BA)', 'slug' => 'business-analyst', 'sort_order' => 3],
            ['name' => 'AI & Automation', 'slug' => 'ai-automation', 'sort_order' => 4],
            ['name' => 'Quản lý dự án', 'slug' => 'project-management', 'sort_order' => 5],
            ['name' => 'Kinh nghiệm thực tế', 'slug' => 'field-experience', 'sort_order' => 6],
            ['name' => 'Tài liệu nội bộ', 'slug' => 'internal-docs', 'sort_order' => 7],
            ['name' => 'Khác', 'slug' => 'other', 'sort_order' => 8],
        ];

        foreach ($categories as $row) {
            DB::table('kb_categories')->insert([
                'name' => $row['name'],
                'slug' => $row['slug'],
                'sort_order' => $row['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_reads');
        Schema::dropIfExists('kb_article_favorites');
        Schema::dropIfExists('kb_article_attachments');
        Schema::dropIfExists('kb_article_images');
        Schema::dropIfExists('kb_article_tags');
        Schema::dropIfExists('kb_tags');
        Schema::dropIfExists('kb_articles');
        Schema::dropIfExists('kb_categories');
    }
};
