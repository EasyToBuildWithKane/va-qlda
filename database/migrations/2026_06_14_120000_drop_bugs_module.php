<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bug_activities');
        Schema::dropIfExists('bugs');
    }

    public function down(): void
    {
        // Module đã gỡ — không khôi phục schema cũ tại đây.
    }
};
