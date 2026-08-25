<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StepTemplate extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'name'];

    public function items(): HasMany
    {
        return $this->hasMany(StepTemplateItem::class)->orderBy('position')->orderBy('id');
    }
}
