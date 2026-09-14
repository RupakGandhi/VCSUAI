<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageEvent extends Model
{
    protected $connection = 'content';

    public $timestamps = false; // only created_at, set via DB default

    protected $fillable = ['event_type', 'module_id', 'role', 'search_term', 'session_id'];

    const EVENT_TYPES = [
        'page_view', 'module_start', 'module_complete', 'sim_used',
        'role_change', 'search', 'certificate_generated',
    ];
}
