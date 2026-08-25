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
            $table->softDeletes();
            $table->foreignId('todo_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('todos', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 5)->default('de')->after('theme');
            $table->string('holiday_region', 2)->default('NW')->after('working_days');
            $table->decimal('vacation_days', 5, 1)->default(30)->after('holiday_region');
            $table->unsignedTinyInteger('focus_minutes')->default(25)->after('vacation_days');
            $table->unsignedTinyInteger('focus_break_minutes')->default(5)->after('focus_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('todo_id');
            $table->dropSoftDeletes();
        });

        Schema::table('todos', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['locale', 'holiday_region', 'vacation_days', 'focus_minutes', 'focus_break_minutes']);
        });
    }
};
