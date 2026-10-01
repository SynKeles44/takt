<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which workflow states the ticket board shows.
 *
 * Kept per user rather than in the browser, because it is a decision about the board and not about
 * the device: a workflow with nine states usually has three worth looking at every day, and that
 * choice should survive opening the app somewhere else. Null means "all of them", which is also
 * what a new account sees — a board that starts out hiding things is a board you cannot trust.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('board_states')->nullable()->after('dashboard_arranged');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('board_states');
        });
    }
};
