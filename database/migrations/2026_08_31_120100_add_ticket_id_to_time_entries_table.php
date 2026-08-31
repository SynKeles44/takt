<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Until now the time per ticket was a guess: a day's work split evenly across the tickets
 * committed that day. This column is what turns it into a measurement — and it stays nullable,
 * because most bookings are not about a ticket and should not be forced to pretend they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('ticket_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ticket_id');
        });
    }
};
