<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PracticePrompt extends Model
{
    protected $connection = 'content';

    use LogsActivity;

    const ROLES = ['classroom', 'leader', 'sped', 'support', 'coach', 'higher_ed'];

    protected $fillable = ['module_id', 'role', 'title', 'ai_tool', 'prompt_text', 'sample_file', 'sort_order'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
