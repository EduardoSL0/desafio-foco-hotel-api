<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
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
        'refunded_at',
        'refunded_by',
    ];

    protected function casts(): array
    {
        return [
            'reserve_id' => 'integer',
            'method' => PaymentMethod::class,
            'value' => 'decimal:2',
            'installments' => 'integer',
            'interest' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'refunded_by' => 'integer',
        ];
    }

    public function reserve(): BelongsTo
    {
        return $this->belongsTo(Reserve::class);
    }

    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }

    /** Pagamentos que contam como dinheiro recebido (não estornados). */
    public function scopeReceived(Builder $query): Builder
    {
        return $query->whereNull('refunded_at');
    }
}
