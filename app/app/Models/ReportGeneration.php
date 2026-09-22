<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportGeneration extends Model
{
    protected $fillable = [
        'chart_id', 'report_type', 'filename', 'size_bytes', 'checksum', 'door', 'ai_assisted', 'ai_model',
        'input_tokens', 'output_tokens', 'total_tokens', 'cost_input', 'cost_output', 'cost_subtotal',
        'tax_rate', 'tax_amount', 'cost_total', 'cost_currency', 'system_prompt', 'user_prompt',
    ];

    protected $casts = [
        'ai_assisted' => 'boolean',
        'cost_input' => 'decimal:8',
        'cost_output' => 'decimal:8',
        'cost_subtotal' => 'decimal:8',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:8',
        'cost_total' => 'decimal:8',
    ];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(Chart::class);
    }
}
