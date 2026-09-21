<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    protected $fillable = [
        'alias',
        'full_name',
        'residence_place_id',
        'notes',
    ];

    public function residencePlace(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'residence_place_id');
    }

    public function birthData(): HasMany
    {
        return $this->hasMany(BirthData::class);
    }

    public function charts(): HasMany
    {
        return $this->hasMany(Chart::class);
    }
}
