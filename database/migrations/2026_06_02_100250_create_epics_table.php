<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 24)->default('violet');
            $table->timestamps();

            $table->unique(['project_id', 'name']);
            $table->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epics');
    }
};
