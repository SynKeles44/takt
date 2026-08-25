<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('weekly_hours', 5, 2)->default(40);
            $table->unsignedTinyInteger('working_days')->default(5);
            $table->string('theme', 20)->default('midnight');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['weekly_hours', 'working_days', 'theme']);
        });
    }
};
