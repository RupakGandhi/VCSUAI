<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class GsBlock extends Model
{
    protected $connection = 'content';

    use LogsActivity;

    protected $fillable = ['gs_page_id', 'title', 'content_html', 'sort_order'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(GsPage::class, 'gs_page_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
