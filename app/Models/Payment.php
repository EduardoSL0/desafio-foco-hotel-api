<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'reserve_id',
        'method',
        'value',
        'installments',
        'interest',
        'source',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'value' => 'decimal:2',
            'installments' => 'integer',
            'interest' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function reserve(): BelongsTo
    {
        return $this->belongsTo(Reserve::class);
    }
}
