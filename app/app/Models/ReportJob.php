<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportJob extends Model
{
    protected $guarded = [];

    protected $attributes = ['status' => 'queued', 'cursor' => 0, 'progress' => 0];

    protected $casts = [
        'user_id' => 'integer', 'chart_id' => 'integer',
        'doors' => 'array', 'drafts' => 'array', 'cursor' => 'integer', 'progress' => 'integer',
        'started_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(Chart::class);
    }
}
