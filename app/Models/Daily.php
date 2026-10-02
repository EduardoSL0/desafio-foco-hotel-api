<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Daily extends Model
{
    protected $fillable = [
        'reserve_id',
        'date',
        'value',
        'discount',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value' => 'decimal:2',
            'discount' => 'decimal:2',
        ];
    }

    public function reserve(): BelongsTo
    {
        return $this->belongsTo(Reserve::class);
    }
}
