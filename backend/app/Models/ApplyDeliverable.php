<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApplyDeliverable extends Model
{
    protected $connection = 'content';

    use LogsActivity;

    protected $fillable = ['module_id', 'role', 'title', 'description', 'initial_prompt', 'refine_prompt', 'sort_order'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
