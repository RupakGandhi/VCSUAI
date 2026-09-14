<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GsPage extends Model
{
    protected $connection = 'content';

    use LogsActivity;

    protected $fillable = ['title', 'description_html', 'sort_order'];

    public function blocks(): HasMany
    {
        return $this->hasMany(GsBlock::class)->orderBy('sort_order');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
