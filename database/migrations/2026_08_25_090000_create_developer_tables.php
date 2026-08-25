<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('repository')->nullable();
            $table->string('start_command')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'path']);
        });

        Schema::create('snippets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('label')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'label']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('github_token')->nullable()->after('notify_worktime');
            $table->string('ticket_url_template')->nullable()->after('github_token');
            $table->string('pr_url_template')->nullable()->after('ticket_url_template');
            $table->string('instance_url_template')->nullable()->after('pr_url_template');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snippets');
        Schema::dropIfExists('projects');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['github_token', 'ticket_url_template', 'pr_url_template', 'instance_url_template']);
        });
    }
};
