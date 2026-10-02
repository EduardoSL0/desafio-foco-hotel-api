<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Support\Money;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'code',
        'type',
        'value',
        'valid_from',
        'valid_until',
        'max_uses',
        'used_count',
        'active',
    ];

    protected $attributes = [
        'used_count' => 0,
        'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'hotel_id' => 'integer',
            'type' => DiscountType::class,
            'value' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function isValidFor(int $hotelId, ?DateTimeInterface $on = null): bool
    {
        $on = Carbon::instance($on ?? now())->startOfDay();

        return $this->active
            && ($this->hotel_id === null || $this->hotel_id === $hotelId)
            && ($this->valid_from === null || $this->valid_from->lte($on))
            && ($this->valid_until === null || $this->valid_until->gte($on))
            && ($this->max_uses === null || $this->used_count < $this->max_uses);
    }

    /** Valor do desconto (em centavos) sobre uma base, nunca maior que a própria base. */
    public function discountFor(int $baseCents): int
    {
        $discount = match ($this->type) {
            DiscountType::Percent => Money::percentOf($baseCents, $this->value),
            DiscountType::Fixed => Money::toCents($this->value),
        };

        return max(0, min($discount, $baseCents));
    }
}
