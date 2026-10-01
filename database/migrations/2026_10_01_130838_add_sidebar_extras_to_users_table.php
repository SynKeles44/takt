<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // null rather than an empty array: nobody has pinned anything yet, and that is not the
        // same statement as "this person pinned nothing"
        Schema::table('users', fn (Blueprint $table) => $table->json('sidebar_extras')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('sidebar_extras'));
    }
};
