<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Until now every run was `make <target>`, so the target alone described the command. A package
 * update is the same machinery — detached process, own log, exit code in a file — with a
 * different command, and this column is what tells the two apart when the log is read back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('command_runs', function (Blueprint $table): void {
            $table->string('kind', 16)->default('make')->after('target');
        });
    }

    public function down(): void
    {
        Schema::table('command_runs', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
