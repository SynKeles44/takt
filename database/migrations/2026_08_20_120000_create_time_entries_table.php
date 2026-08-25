<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['started_at']);
            $table->index(['type', 'started_at']);
            $table->index(['ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
