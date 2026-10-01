<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // null is "the order the app ships with", which is not the same statement as a stored
        // list that happens to match it — a section added later joins the end of a stored list
        // and is simply in its place in the shipped one
        Schema::table('users', fn (Blueprint $table) => $table->json('sidebar_order')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('sidebar_order'));
    }
};
