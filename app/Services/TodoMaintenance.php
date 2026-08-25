<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Todo;
use Illuminate\Support\Carbon;

final class TodoMaintenance
{
    public function run(): int
    {
        $expired = Todo::query()
            ->open()
            ->dated()
            ->where('due_at', '<', Carbon::now())
            ->with('tags')
            ->get()
            ->filter(fn (Todo $todo): bool => $todo->autoCompletes());

        foreach ($expired as $todo) {
            $todo->update(['completed_at' => $todo->due_at]);
        }

        return $expired->count();
    }
}
