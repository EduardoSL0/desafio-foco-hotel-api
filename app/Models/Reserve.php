<?php

namespace App\Models;

use App\Enums\ReserveStatus;
use App\Support\Money;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reserve extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'room_id',
        'coupon_id',
        'external_code',
        'check_in',
        'check_out',
        'subtotal',
        'discount',
        'fees',
        'total',
        'status',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'hotel_id' => 'integer',
            'room_id' => 'integer',
            'check_in' => 'date',
            'check_out' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'fees' => 'decimal:2',
            'total' => 'decimal:2',
            'status' => ReserveStatus::class,
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function guests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class);
    }

    public function dailies(): HasMany
    {
        return $this->hasMany(Daily::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Reservas que ocupam inventário (todas exceto as canceladas). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', ReserveStatus::Cancelled->value);
    }

    /**
     * Reservas cujo período cruza o intervalo [checkIn, checkOut).
     * O dia do check-out não ocupa o quarto, permitindo reservas "encostadas".
     */
    public function scopeOverlapping(Builder $query, DateTimeInterface $checkIn, DateTimeInterface $checkOut): Builder
    {
        return $query
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn);
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    public function paidAmount(): float
    {
        $sum = $this->relationLoaded('payments')
            ? $this->payments->sum(fn (Payment $p) => Money::toCents($p->value))
            : Money::toCents($this->payments()->sum('value'));

        return Money::fromCents((int) $sum);
    }

    public function balance(): float
    {
        return Money::fromCents(max(0, Money::toCents($this->total) - Money::toCents($this->paidAmount())));
    }

    /** Recalcula o status financeiro da reserva com base nos pagamentos registrados. */
    public function refreshStatus(): void
    {
        if ($this->status === ReserveStatus::Cancelled) {
            return;
        }

        $this->unsetRelation('payments');

        $paid = Money::toCents($this->paidAmount());
        $total = Money::toCents($this->total);

        $this->status = match (true) {
            $paid >= $total => ReserveStatus::Paid,
            $paid > 0 => ReserveStatus::PartiallyPaid,
            default => ReserveStatus::Pending,
        };

        $this->save();
    }
}
