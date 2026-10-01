<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The day columns leave the table.
 *
 * Takt used to keep a second board here — today, next, waiting, parked, done — alongside Linear's
 * own workflow. That meant a ticket's state lived in two places and could be moved in two ways, so
 * the board became Linear's and these four lost their last reader a change ago.
 *
 * This deletes data, and deliberately: the sorting stored in them is the thing being retired, and
 * leaving it behind would make the next person wonder which of the two boards is the real one.
 * `down` rebuilds the shape but cannot bring the values back — a backup is the only thing that
 * can, which is why one was taken before this ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        // the index goes first: SQLite rebuilds the table on a drop and validates every index on it
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex('tickets_user_id_column_position_index');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn(['column', 'position', 'column_changed_at', 'waiting_reason']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('column')->nullable()->after('body');
            $table->integer('position')->default(0)->after('column');
            $table->timestamp('column_changed_at')->nullable()->after('position');
            $table->string('waiting_reason')->nullable()->after('column_changed_at');
            $table->index(['user_id', 'column', 'position']);
        });
    }
};
