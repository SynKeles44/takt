<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->text('body')->nullable()->after('title');
            $table->timestamp('due_at')->nullable()->after('body');
            $table->boolean('due_has_time')->default(false)->after('due_at');

            $table->index(['user_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'due_at']);
            $table->dropColumn(['body', 'due_at', 'due_has_time']);
        });
    }
};
