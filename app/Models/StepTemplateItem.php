<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepTemplateItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['step_template_id', 'title', 'position'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(StepTemplate::class, 'step_template_id');
    }
}
