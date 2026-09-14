<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Simulation extends Model
{
    protected $connection = 'content';

    use LogsActivity;

    protected $fillable = [
        'module_id', 'role', 'title', 'prompt_text', 'keywords', 'response',
        'verification_tips', 'followup_options', 'bias_check_tips', 'expected_filename', 'requires_file_upload', 'sort_order',
    ];

    protected $casts = [
        'keywords' => 'array',
        'verification_tips' => 'array',
        'followup_options' => 'array',
        'bias_check_tips' => 'array',
        'requires_file_upload' => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
