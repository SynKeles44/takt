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
            // set together they win over the day window, so a chosen range survives a reload
            $table->date('home_office_from')->nullable()->after('home_office_window');
            $table->date('home_office_to')->nullable()->after('home_office_from');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['home_office_from', 'home_office_to']);
        });
    }
};
