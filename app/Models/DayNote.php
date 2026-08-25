<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class DayNote extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'day', 'body'];

    protected $casts = ['day' => 'date'];
}
