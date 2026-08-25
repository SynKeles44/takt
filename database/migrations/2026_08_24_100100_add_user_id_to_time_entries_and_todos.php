<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'started_at']);
        });

        Schema::table('todos', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'started_at']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('todos', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'completed_at']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
