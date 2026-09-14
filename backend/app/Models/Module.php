<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Module extends Model
{
    protected $connection = 'content';

    use LogsActivity;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'course_id', 'title', 'sort_order', 'requires_file_upload'];

    protected $casts = [
        'requires_file_upload' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function content(): HasOne
    {
        return $this->hasOne(ModuleContent::class);
    }

    public function practicePrompts(): HasMany
    {
        return $this->hasMany(PracticePrompt::class)->orderBy('role')->orderBy('sort_order');
    }

    public function applyDeliverables(): HasMany
    {
        return $this->hasMany(ApplyDeliverable::class)->orderBy('role')->orderBy('sort_order');
    }

    public function masteryPrompts(): HasMany
    {
        return $this->hasMany(MasteryPrompt::class)->orderBy('role')->orderBy('sort_order');
    }

    public function simulations(): HasMany
    {
        return $this->hasMany(Simulation::class)->orderBy('role')->orderBy('sort_order');
    }

    public function overviewSections(): HasMany
    {
        return $this->hasMany(ModuleOverviewSection::class)
            ->orderBy('section')
            ->orderBy('role')
            ->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
