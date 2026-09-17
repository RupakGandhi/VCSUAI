<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    protected $connection = 'content';

    public $timestamps = false; // only created_at via DB default

    protected $fillable = ['module_id', 'q1_score', 'q2_score', 'q3_text', 'role', 'session_id', 'is_test'];

    protected $casts = [
        'q1_score'   => 'integer',
        'q2_score'   => 'integer',
        'created_at' => 'datetime',
        'is_test'    => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Rows flagged is_test (a known QA/retest submission, e.g. one entered
     * to verify the survey pipeline against production) shouldn't count
     * toward evaluation -- but the only way to remove them before this flag
     * existed was deleting a whole module's or the whole client's responses.
     * Widgets/exports reporting on real participant feedback should query
     * through this scope instead of SurveyResponse directly.
     */
    public function scopeReal($query)
    {
        return $query->where('is_test', false);
    }
}
