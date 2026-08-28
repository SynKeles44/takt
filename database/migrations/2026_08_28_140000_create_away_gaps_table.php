<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('away_gaps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('away_gaps');
    }
};
