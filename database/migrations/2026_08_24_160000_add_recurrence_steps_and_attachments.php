<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->string('recurrence', 16)->default('none')->after('due_has_time');
        });

        Schema::create('todo_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['todo_id', 'position']);
        });

        Schema::create('todo_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('path', 300);
            $table->string('mime', 120);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_attachments');
        Schema::dropIfExists('todo_steps');

        Schema::table('todos', function (Blueprint $table): void {
            $table->dropColumn('recurrence');
        });
    }
};
