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
            // a user token (xoxp-), so a post appears under the user's own name
            $table->text('slack_token')->nullable()->after('github_token');
            $table->string('slack_channel')->nullable()->after('slack_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['slack_token', 'slack_channel']);
        });
    }
};
