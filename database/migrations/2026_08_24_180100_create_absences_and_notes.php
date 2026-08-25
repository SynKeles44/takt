<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_on']);
        });

        Schema::create('day_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->text('body');
            $table->timestamps();

            $table->unique(['user_id', 'day']);
        });

        Schema::create('step_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('step_template_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('step_template_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->unsignedInteger('position')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_template_items');
        Schema::dropIfExists('step_templates');
        Schema::dropIfExists('day_notes');
        Schema::dropIfExists('absences');
    }
};
