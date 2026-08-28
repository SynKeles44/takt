<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_spans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('app');
            $table->string('title')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('activity_trail')->default(false)->after('home_office_window');
            $table->unsignedSmallInteger('activity_retention_days')->default(30)->after('activity_trail');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_spans');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['activity_trail', 'activity_retention_days']);
        });
    }
};
