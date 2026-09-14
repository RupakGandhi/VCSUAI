<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PlatformSetting extends Model
{
    protected $connection = 'content';

    use LogsActivity;

    protected $fillable = [
        'title', 'subtitle', 'page_title', 'hero_title', 'hero_desc', 'hero_stats',
        'primary_color', 'accent_color', 'password', 'fac_password',
        'survey_q1_text', 'survey_q2_text', 'survey_q3_text', 'footer_html', 'role_labels', 'ai_tool_name',
        'icon_192', 'icon_512', 'icon_512_maskable',
    ];

    protected $casts = [
        'hero_stats' => 'array',
        'role_labels' => 'array',
    ];

    /**
     * Always operate on the single settings row, creating it if missing.
     * Looks up the FIRST row rather than a hardcoded id=1: 'id' isn't in
     * $fillable (and shouldn't be, for a normal auto-increment key), so
     * firstOrCreate(['id' => 1], ...) could never actually match an
     * existing row and would silently create a new blank one on every call.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::create(['title' => 'VCSU AI Institute for Teaching and Learning']);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
