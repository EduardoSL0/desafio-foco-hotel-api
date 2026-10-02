<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hotel_id',
        'external_code',
        'name',
        'description',
        'capacity',
        'inventory',
        'daily_price',
    ];

    protected $attributes = [
        'capacity' => 2,
        'inventory' => 1,
    ];

    protected function casts(): array
    {
        return [
            'hotel_id' => 'integer',
            'capacity' => 'integer',
            'inventory' => 'integer',
            'daily_price' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function reserves(): HasMany
    {
        return $this->hasMany(Reserve::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }
}
