<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interpretation extends Model
{
    protected $fillable = [
        'chart_id',
        'template_id',
        'phase',
        'door',
        'block',
        'content',
        'ai_assisted',
        'rulers_used',
    ];

    protected $casts = [
        'ai_assisted' => 'boolean',
        'rulers_used' => 'array',
    ];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(Chart::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChartTemplate::class, 'template_id');
    }
}
