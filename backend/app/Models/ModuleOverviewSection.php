<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ModuleOverviewSection extends Model
{
    protected $connection = 'content';

    use LogsActivity;

    protected $fillable = ['module_id', 'section', 'role', 'content', 'sort_order'];

    const SECTIONS = ['challenge', 'concept', 'matters', 'strategy'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
