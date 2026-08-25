<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('command_runs', function (Blueprint $table): void {
            // a run inside a pseudo terminal can be typed into; one without cannot
            $table->boolean('interactive')->default(false)->after('target');
        });
    }

    public function down(): void
    {
        Schema::table('command_runs', function (Blueprint $table): void {
            $table->dropColumn('interactive');
        });
    }
};
