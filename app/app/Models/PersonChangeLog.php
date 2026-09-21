<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonChangeLog extends Model
{
    protected $fillable = ['person_id', 'chart_id', 'field', 'old_value', 'new_value'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(Chart::class);
    }
}
