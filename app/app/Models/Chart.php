<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chart extends Model
{
    protected $fillable = [
        'person_id',
        'birth_data_id',
        'configuration',
        'engine_version',
        'snapshot',
        'status',
        'phase_one_pdf',
        'phase_one_pdf_generated_at',
    ];

    protected $casts = [
        'configuration' => 'array',
        'snapshot' => 'array',
        'phase_one_pdf_generated_at' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function birthData(): BelongsTo
    {
        return $this->belongsTo(BirthData::class);
    }

    public function interpretations(): HasMany
    {
        return $this->hasMany(Interpretation::class);
    }

    public function reportGenerations(): HasMany
    {
        return $this->hasMany(ReportGeneration::class);
    }
}
