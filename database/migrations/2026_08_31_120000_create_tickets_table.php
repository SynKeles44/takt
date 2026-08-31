<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The local layer over a ticket. Linear owns the issue — its title, state and priority live
 * there and are fetched. This table holds what Linear must never see: which column of my day a
 * ticket sits in, my own estimate, my private notes, and the ids I decided to ignore.
 *
 * A row exists for a Linear issue only once something local was said about it, so an untouched
 * account creates no rows at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // `COR-6950` for a Linear issue, `TAKT-1` for one that exists only here
            $table->string('key', 32);
            $table->string('source', 8)->default('linear');

            // only local tickets own their title; for Linear ones this caches the last seen one
            $table->string('title')->nullable();
            $table->text('body')->nullable();

            // my day, not Linear's workflow: today, next, waiting, parked, done — null = off board
            $table->string('column', 16)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('column_changed_at')->nullable();
            $table->string('waiting_reason')->nullable();

            $table->unsignedInteger('estimate_seconds')->nullable();
            $table->text('notes')->nullable();

            // one ticket at a time may be the current focus; the menu bar reads it
            $table->timestamp('focused_at')->nullable();

            // a git-only id I never want to see again, and where a promoted local ticket landed
            $table->timestamp('ignored_at')->nullable();
            $table->string('promoted_url')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'column', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
