<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'room_id',
        'name',
        'discount_percent',
        'starts_at',
        'ends_at',
        'active',
    ];

    protected $attributes = [
        'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'hotel_id' => 'integer',
            'room_id' => 'integer',
            'discount_percent' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** Promoções ativas do quarto (ou do hotel inteiro) que cruzam o período informado. */
    public function scopeApplicableTo(Builder $query, Room $room, DateTimeInterface $from, DateTimeInterface $to): Builder
    {
        return $query
            ->where('active', true)
            ->where('hotel_id', $room->hotel_id)
            ->where(fn (Builder $q) => $q->whereNull('room_id')->orWhere('room_id', $room->id))
            ->where('starts_at', '<=', $to)
            ->where('ends_at', '>=', $from);
    }

    public function covers(DateTimeInterface $date): bool
    {
        return $this->starts_at->lte($date) && $this->ends_at->gte($date);
    }
}
