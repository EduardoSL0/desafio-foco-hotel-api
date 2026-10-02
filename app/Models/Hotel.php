<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_code',
        'name',
        'service_fee_percent',
    ];

    protected function casts(): array
    {
        return [
            'service_fee_percent' => 'decimal:2',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function reserves(): HasMany
    {
        return $this->hasMany(Reserve::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }
}
