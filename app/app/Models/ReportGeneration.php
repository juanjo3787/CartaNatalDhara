<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportGeneration extends Model
{
    protected $fillable = ['chart_id', 'report_type', 'filename', 'size_bytes', 'checksum'];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(Chart::class);
    }
}
