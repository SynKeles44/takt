<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('color', 16)->default('accent');
            $table->unsignedInteger('warn_lead_minutes')->default(60);
            $table->boolean('auto_complete_expired')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('todo_tag', function (Blueprint $table): void {
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['todo_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_tag');
        Schema::dropIfExists('tags');
    }
};
