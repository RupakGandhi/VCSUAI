<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    protected $connection = 'content';

    public $timestamps = false; // only created_at via DB default

    protected $fillable = ['module_id', 'q1_score', 'q2_score', 'q3_text', 'role', 'session_id'];

    protected $casts = [
        'q1_score'   => 'integer',
        'q2_score'   => 'integer',
        'created_at' => 'datetime',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
