<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartTemplate extends Model
{
    protected $table = 'templates';

    protected $fillable = [
        'name',
        'door',
        'block',
        'content_type',
        'version',
        'status',
        'content',
    ];

    public function interpretations(): HasMany
    {
        return $this->hasMany(Interpretation::class, 'template_id');
    }
}
