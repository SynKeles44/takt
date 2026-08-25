<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array MAP = [
        'swiss' => 'minimal',
        'factory' => 'industrial',
        'glass' => 'glassmorphism',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('users')->where('design_style', $old)->update(['design_style' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('users')->where('design_style', $new)->update(['design_style' => $old]);
        }
    }
};
