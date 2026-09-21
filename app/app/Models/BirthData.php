<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthData extends Model
{
    protected $table = 'birth_data';

    protected $fillable = [
        'person_id',
        'place_id',
        'local_date',
        'local_time',
        'timezone_identifier',
        'utc_offset',
        'utc_datetime',
        'time_source',
        'time_precision',
    ];

    protected $casts = [
        'local_date' => 'date',
        'utc_datetime' => 'datetime',
    ];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
